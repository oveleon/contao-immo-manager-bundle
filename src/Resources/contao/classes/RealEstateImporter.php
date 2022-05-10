<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/core
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

namespace ContaoEstateManager;

use Contao\BackendModule;
use Contao\BackendTemplate;
use Contao\Database;
use Contao\Dbafs;
use ContaoEstateManager\EstateManager\Exception\ImportException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Contao\Files;
use Contao\FilesModel;
use Contao\Folder;
use Contao\Input;
use Contao\StringUtil;
use Contao\System;
use Contao\ZipReader;

class RealEstateImporter extends BackendModule
{

    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'be_real_estate_sync';

    /**
     * Messages
     * @var array
     */
    protected $messages = array();

    /**
     * Interface
     * @var InterfaceModel
     */
    protected $objInterface;

    /**
     * Import folder model
     * @var FilesModel
     */
    protected $objImportFolder;

    /**
     * Import folder model
     * @var FilesModel
     */
    protected $objFilesFolder;

    /**
     * Import folder model for contact person
     * @var FilesModel
     */
    protected $objFilesFolderContactPerson;

    /**
     * Import folder model
     * @var \Model\Collection
     */
    protected $objInterfaceMapping;

    /**
     * Import Logger
     */
    protected $logger;

    /**
     * Path of sync file
     * @var string
     */
    protected $syncFile;

    /**
     * Path of original sync file
     * @var string
     */
    public $originalSyncFile;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var boolean
     */
    public $updateSyncTime = true;

    /**
     * @var string
     */
    protected $uniqueProviderValue;

    /**
     * @var string
     */
    protected $uniqueValue;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var int
     */
    public $importStatus = 1;

    /**
     * @var string
     */
    public $importMessage = 'File imported.';

    /**
     * @var boolean
     */
    public $skipRecord = false;

    /**
     * @var boolean
     */
    public $skipContactPerson = false;

    /**
     * Set an object property
     *
     * @param string $strKey
     * @param mixed  $varValue
     */
    public function __set($strKey, $varValue)
    {
        switch ($strKey)
        {
            case 'username':
                $this->username = $varValue;
                break;
            case 'data':
                $this->data = $varValue;
                break;
            case 'uniqueProviderValue':
                $this->uniqueProviderValue = $varValue;
                break;
        }

        parent::__set($strKey, $varValue);
    }

    /**
     * Return an object property
     *
     * @param string $strKey
     *
     * @return mixed
     */
    public function __get($strKey)
    {
        switch ($strKey)
        {
            case 'interface':
                return $this->objInterface;
            case 'importFolder':
                return $this->objImportFolder;
            case 'filesFolder':
                return $this->objFilesFolder;
            case 'filesFolderContactPerson':
                return $this->objFilesFolderContactPerson;
            case 'interfaceMapping':
                return $this->objInterfaceMapping;
            case 'uniqueProviderValue':
                return $this->uniqueProviderValue;
            case 'uniqueValue':
                return $this->uniqueValue;
            case 'username':
                return $this->username;
            case 'data':
                return $this->data;
            case 'syncFile':
                return $this->syncFile;
        }

        return parent::__get($strKey);
    }

    /**
     * Generate module
     */
    protected function compile() {}

    /**
     * Prepare interface, file models and interface mappings
     *
     * @param integer $id
     *
     * @return boolean
     */
    public function initializeInterface($id)
    {
        $this->logger = new ImportLogger();
        $this->objInterface = InterfaceModel::findByPk($id);

        if ($this->objInterface === null)
        {
            return false;
        }

        $this->objImportFolder = FilesModel::findByUuid($this->objInterface->importPath);
        $this->objFilesFolder = FilesModel::findByUuid($this->objInterface->filesPath);

        if ($this->objImportFolder === null || $this->objFilesFolder === null)
        {
            return false;
        }

        $this->objFilesFolderContactPerson = FilesModel::findByUuid($this->objInterface->filesPathContactPerson);

        $arrOptions = array('order'=>'saveImage');

        $this->objInterfaceMapping = InterfaceMappingModel::findByPid($id, $arrOptions);

        if ($this->objInterfaceMapping === null)
        {
            return false;
        }

        return true;
    }

    /**
     * Syncs OpenImmo export data with database
     *
     * @param \DataContainer $dc
     *
     * @return string
     */
    public function sync($dc)
    {
        if (!$this->initializeInterface($dc->id))
        {
            $this->logger->error('Interface could not been initialized.');
        }

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['realEstateImportBeforeSync']) && \is_array($GLOBALS['TL_HOOKS']['realEstateImportBeforeSync']))
        {
            foreach ($GLOBALS['TL_HOOKS']['realEstateImportBeforeSync'] as $callback)
            {
                $this->import($callback[0]);
                $this->{$callback[0]}->{$callback[1]}($this);
            }
        }

        $this->import('BackendUser', 'User');
        $this->username = $this->User->username;

        if (Input::post('FORM_SUBMIT') === 'tl_real_estate_import' && ($this->syncFile = html_entity_decode(Input::post('file'))) !== '')
        {
            $this->originalSyncFile = $this->syncFile;

            if (($this->syncFile = $this->getSyncFile($this->syncFile)) !== false)
            {
                $this->startSync();
            }
            else
            {
                $this->logger->error('OpenImmo file could not be loaded.');
            }
        }

        System::loadLanguageFile('tl_real_estate_sync');

        $this->Template = new BackendTemplate($this->strTemplate);

        $files = $this->getSyncFiles();

        $this->Template->setData(array
        (
            'syncAvailable' => $this->objInterface->type === 'wib' || $this->objInterface->type === 'onofficeapi', // ToDo. Remove from bundle
            'type'          => $this->objInterface->type,
            'syncUrl'       => 'syncUrl',
            'files'         => $files,
            'messages'      => $this->messages
        ));

        return $this->Template->parse();
    }

    /**
     * Syncs OpenImmo export data with database
     *
     * @param string $syncFile
     */
    public function startSync($syncFile='')
    {
        if ($syncFile !== '')
        {
            $this->syncFile = $syncFile;
        }

        @ini_set('max_execution_time', 0);

        // Consider the suhosin.memory_limit (see contao#7035)
        if (\extension_loaded('suhosin'))
        {
            if (($limit = ini_get('suhosin.memory_limit')) !== '')
            {
                @ini_set('memory_limit', $limit);
            }
        }
        else
        {
            @ini_set('memory_limit', -1);
        }

        $skip = false;

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['realEstateImportBeforeLoadData']) && \is_array($GLOBALS['TL_HOOKS']['realEstateImportBeforeLoadData']))
        {
            foreach ($GLOBALS['TL_HOOKS']['realEstateImportBeforeLoadData'] as $callback)
            {
                $this->import($callback[0]);
                $skip = $this->{$callback[0]}->{$callback[1]}($this);
            }
        }

        if ($skip)
        {
            return;
        }

        if (($this->loadData()))
        {
            $this->logger->info('OpenImmo file loaded successfully. File import is started.', [
                'file' => $this->syncFile
            ]);

            if ($this->syncData())
            {
                $this->logger->info('Import and synchronization was successful.');
            }
            else
            {
                $this->logger->error('OpenImmo data could not be synchronized.');
            }
        }
        else
        {
            $this->logger->error('OpenImmo data could not be loaded.', [
                'file' => $this->syncFile
            ]);
        }
    }

    /**
     * Sync OpenImmo data with database
     *
     * @return boolean
     */
    protected function syncData()
    {
        if ($this->data->getName() !== 'openimmo')
        {
            $this->logger->error('Invalid OpenImmo data.');
        }

        $arrProvider = $this->data->xpath('anbieter');

        if (\count($arrProvider) === 0)
        {
            $this->logger->error('No provider data available.');
        }

        $skipRecords = StringUtil::deserialize($this->objInterface->skipRecords, true);

        $contactPersonMeta = $this->getTableMetaData('tl_contact_person');
        $realEstateMeta = $this->getTableMetaData('tl_real_estate');

        $contactPersonRecords = array();
        $realEstateRecords = array();

        foreach ($arrProvider as $provider)
        {
            $this->uniqueProviderValue = trim(current($provider->{$this->objInterface->uniqueProviderField}));

            if (empty($this->uniqueProviderValue))
            {
                $this->uniqueProviderValue = $this->objInterface->anbieternr;
            }

            if (!$this->objInterface->importThirdPartyRecords && $this->objInterface->anbieternr !== $this->uniqueProviderValue)
            {
                $this->logger->info('Skip real estate due to missing provider.', null, ImportLogger::LOG_PROD);
                continue;
            }

            if ($this->objInterface->importThirdPartyRecords === 'import')
            {
                $objProvider = ProviderModel::findOneByAnbieternr($this->uniqueProviderValue);

                if ($objProvider === null)
                {
                    $this->importStatus = 2;
                    $this->importMessage = 'File partially imported.';

                    continue;
                }
            }

            $arrRealEstate = $provider->xpath('immobilie');

            foreach ($arrRealEstate as $realEstate)
            {
                $skip = false;
                $this->uniqueValue = $this->getUniqueValue($realEstate);

                $contactPerson = array();
                $re = array
                (
                    'ANBIETER' => $this->uniqueProviderValue,
                    'AKTIONART' => current($realEstate->verwaltung_techn->aktion)['aktionart']
                );

                // HOOK: add custom logic
                if (isset($GLOBALS['TL_HOOKS']['realEstateImportPrePrepareRecord']) && \is_array($GLOBALS['TL_HOOKS']['realEstateImportPrePrepareRecord']))
                {
                    foreach ($GLOBALS['TL_HOOKS']['realEstateImportPrePrepareRecord'] as $callback)
                    {
                        $this->import($callback[0]);
                        $this->{$callback[0]}->{$callback[1]}($realEstate, $re, $contactPerson, $skip, $this);
                    }
                }

                if ($skip)
                {
                    continue;
                }

                $this->logger->info('Import real estate.', [
                    'id' => $this->uniqueValue
                ]);

                while ($this->objInterfaceMapping->next())
                {
                    $interfaceMapping = $this->objInterfaceMapping->current();

                    $groups = $realEstate->xpath($interfaceMapping->oiFieldGroup);
                    $values = array();

                    foreach ($groups as $group)
                    {
                        // Skip if condition dont match
                        if ($interfaceMapping->oiConditionField && $interfaceMapping->oiConditionValue)
                        {
                            if (!$this->isValidCondition($interfaceMapping->oiConditionValue, $this->getFieldData($interfaceMapping->oiConditionField, $group)))
                            {
                                if ($interfaceMapping->forceActive)
                                {
                                    switch ($interfaceMapping->type)
                                    {
                                        case 'tl_contact_person':
                                            $contactPerson[$interfaceMapping->attribute] = $interfaceMapping->forceValue;
                                            break;
                                        case 'tl_real_estate':
                                            $re[$interfaceMapping->attribute] = $interfaceMapping->forceValue;
                                            break;
                                    }
                                }

                                continue;
                            }
                        }

                        $field = $interfaceMapping->oiField;

                        if (strrpos($field, '/') !== false)
                        {
                            $tmpGroup = $group;
                            list($group, $field) = $this->getPath($group, $field);
                        }

                        $value = $this->getFieldData($field, $group);

                        // Skip if value is not set
                        if ($value === null)
                        {
                            continue;
                        }

                        // Save image if needed
                        if ($re['AKTIONART'] !== 'DELETE' && $interfaceMapping->saveImage && !$this->saveImage($interfaceMapping, $tmpGroup, $value, $values))
                        {
                            continue;
                        }

                        $value = $this->formatValue($value);

                        $values[] = $value;
                    }

                    if (\in_array($field, $skipRecords))
                    {
                        if (!\count($values) || !$values[0])
                        {
                            $this->objInterfaceMapping->reset();
                            continue 2;
                        }
                    }

                    if (!\count($values))
                    {
                        switch ($interfaceMapping->type)
                        {
                            case 'tl_contact_person':
                                if (array_key_exists($interfaceMapping->attribute, $contactPersonMeta))
                                {
                                    $contactPerson[$interfaceMapping->attribute] = $contactPersonMeta[$interfaceMapping->attribute]['default'];
                                }
                                break;
                            case 'tl_real_estate':
                                if (array_key_exists($interfaceMapping->attribute, $realEstateMeta))
                                {
                                    $re[$interfaceMapping->attribute] = $realEstateMeta[$interfaceMapping->attribute]['default'];
                                }
                                break;
                        }

                        continue;
                    }

                    $tmpValue = $interfaceMapping->serialize ? serialize($values) : $values[0];

                    switch ($interfaceMapping->type)
                    {
                        case 'tl_contact_person':
                            $contactPerson[$interfaceMapping->attribute] = $tmpValue;
                            break;
                        case 'tl_real_estate':
                            $re[$interfaceMapping->attribute] = $tmpValue;
                            break;
                    }
                }

                $this->objInterfaceMapping->reset();

                $contactPersonRecords[] = $contactPerson;
                $realEstateRecords[] = $re;

                $this->logger->info('Fields have been assigned.');
            }
        }

        return $this->updateCatalog($contactPersonRecords, $realEstateRecords);
    }

    /**
     * Checks if the condition is valid
     *
     * @param $strCondition
     * @param $fieldValue
     *
     * @return bool
     */
    protected function isValidCondition($strCondition, $fieldValue)
    {
        $arrConditionValues = explode('|', $strCondition);

        if(\count($arrConditionValues) > 1)
        {
            foreach ($arrConditionValues as $conditionValue)
            {
                if($conditionValue === $fieldValue)
                {
                    return true;
                }
            }

            return false;
        }
        else
        {
            return $strCondition === $fieldValue;
        }
    }

    /**
     * Sync OpenImmo data with database
     *
     * @param array $contactPersonRecords
     * @param array $realEstateRecords
     *
     * @return boolean
     */
    protected function updateCatalog($contactPersonRecords, $realEstateRecords)
    {
        $actions = StringUtil::deserialize($this->objInterface->contactPersonActions, true);

        $allowCreate = \in_array('create', $actions);
        $allowUpdate = \in_array('update', $actions);

        $this->logger->info('Update database');

        $objProvider = ProviderModel::findByPk($this->objInterface->provider);

        foreach ($contactPersonRecords as $i => $contactPerson)
        {
            if ($this->objInterface->importThirdPartyRecords === 'assign' && $realEstateRecords[$i]['ANBIETER'] !== $this->objInterface->anbieternr)
            {
                if ($realEstateRecords[$i]['vermarktungsartKauf'])
                {
                    $objContactPerson = ContactPersonModel::findByPk($this->objInterface->assignContactPersonKauf);
                }
                else if ($realEstateRecords[$i]['vermarktungsartMietePacht'])
                {
                    $objContactPerson = ContactPersonModel::findByPk($this->objInterface->assignContactPersonMietePacht);
                }
                else if ($realEstateRecords[$i]['vermarktungsartErbpacht'])
                {
                    $objContactPerson = ContactPersonModel::findByPk($this->objInterface->assignContactPersonErbpacht);
                }
                else if ($realEstateRecords[$i]['vermarktungsartLeasing'])
                {
                    $objContactPerson = ContactPersonModel::findByPk($this->objInterface->assignContactPersonLeasing);
                }
            }
            else
            {
                if ($this->objInterface->importThirdPartyRecords === 'import')
                {
                    $objProvider = ProviderModel::findOneByAnbieternr($realEstateRecords[$i]['ANBIETER']);
                }

                if ($realEstateRecords[$i]['AKTIONART'] !== 'DELETE')
                {
                    $this->skipRecord = false;
                    $this->skipContactPerson = false;

                    // HOOK: import third party contact person
                    if (isset($GLOBALS['TL_HOOKS']['importThirdPartyContactPerson']) && \is_array($GLOBALS['TL_HOOKS']['importThirdPartyContactPerson']))
                    {
                        foreach ($GLOBALS['TL_HOOKS']['importThirdPartyContactPerson'] as $callback)
                        {
                            $this->import($callback[0]);
                            $objContactPerson = $this->{$callback[0]}->{$callback[1]}($objProvider, $contactPerson, $realEstateRecords[$i], $allowCreate, $allowUpdate, $this);
                        }
                    }

                    if ($this->skipRecord)
                    {
                        continue;
                    }
                    else if (!$this->skipContactPerson)
                    {
                        list($arrColumns, $arrValues) = $this->getContactPersonParameters($contactPerson, $objProvider);

                        $exists = ContactPersonModel::countBy($arrColumns, $arrValues);

                        // Skip if no contact person found and not allowed to create
                        if (!$allowCreate && !$exists)
                        {
                            $this->logger->info('Skip real estate because no contact person has been assigned or created.', [
                                'id' => $realEstateRecords[$i][$this->objInterface->uniqueField]
                            ], ImportLogger::LOG_PROD);
                            continue;
                        }

                        if (!$exists)
                        {
                            // Create new contact person
                            $objContactPerson = new ContactPersonModel();
                            $objContactPerson->setRow($contactPerson);
                            $objContactPerson->pid = $objProvider->id;
                            $objContactPerson->published = 1;
                            $objContactPerson->save();

                            $this->logger->info('New contact person was added.', [
                                'firstname' => $contactPerson['vorname'],
                                'lastname' => $contactPerson['name']
                            ]);
                        }
                        else
                        {
                            // Find contact person
                            $objContactPerson = ContactPersonModel::findOneBy($arrColumns, $arrValues);
                        }

                        if ($allowUpdate)
                        {
                            // Update contact person
                            foreach ($contactPerson as $field => $value)
                            {
                                $objContactPerson->{$field} = $value;
                            }

                            $objContactPerson->save();

                            $this->logger->info('Contact person was updated.', [
                                'firstname' => $contactPerson['vorname'],
                                'lastname' => $contactPerson['name']
                            ]);
                        }
                    }
                }
            }

            $arrColumns = array($this->objInterface->uniqueField.'=?');
            $arrValues  = array($realEstateRecords[$i][$this->objInterface->uniqueField]);

            $exists = RealEstateModel::countBy($arrColumns, $arrValues);

            if (!$exists && $realEstateRecords[$i]['AKTIONART'] === 'DELETE')
            {
                continue;
            }

            if (!$exists)
            {
                // Create new real estate
                $objRealEstate = new RealEstateModel();
                $objRealEstate->dateAdded = time();
                $objRealEstate->published = $this->objInterface->dontPublishRecords ? '' : '1';

                $this->logger->info('New real estate was added.', [
                    'id' => $realEstateRecords[$i][$this->objInterface->uniqueField]
                ]);
            }
            else
            {
                // Find real estate
                $objRealEstate = RealEstateModel::findOneBy($arrColumns, $arrValues);

                if ($realEstateRecords[$i]['AKTIONART'] === 'DELETE')
                {
                    $preventDelete = false;

                    // HOOK: before real estate import
                    if (isset($GLOBALS['TL_HOOKS']['realEstateImportDeleteRecord']) && is_array($GLOBALS['TL_HOOKS']['realEstateImportDeleteRecord']))
                    {
                        foreach ($GLOBALS['TL_HOOKS']['realEstateImportDeleteRecord'] as $callback)
                        {
                            $this->import($callback[0]);
                            $this->{$callback[0]}->{$callback[1]}($objRealEstate, $objProvider, $preventDelete, $this);
                        }
                    }

                    if ($preventDelete)
                    {
                        continue;
                    }

                    // Delete real estate
                    $this->deleteRealEstateImages($objRealEstate, $objProvider);
                    $objRealEstate->delete();

                    $this->logger->info('Real estate was deleted.', [
                        'id' => $realEstateRecords[$i][$this->objInterface->uniqueField]
                    ]);

                    continue;
                }else{
                    $this->logger->info('Real estate was updated.', [
                        'id' => $realEstateRecords[$i][$this->objInterface->uniqueField]
                    ]);
                }
            }

            if ($realEstateRecords[$i]['AKTIONART'] === 'REFERENZ')
            {
                $objRealEstate->referenz = 1;
            }

            $realEstateRecords[$i]['anbieternr'] = $realEstateRecords[$i]['ANBIETER'];

            unset($realEstateRecords[$i]['ANBIETER']);
            unset($realEstateRecords[$i]['AUFTRAGSART']);
            unset($realEstateRecords[$i]['AKTIONART']);

            foreach ($realEstateRecords[$i] as $field => $value)
            {
                $objRealEstate->{$field} = $value;
            }

            $objRealEstate->provider = $objProvider->id;
            $objRealEstate->contactPerson = $objContactPerson->id;
            $objRealEstate->tstamp = time();

            $this->loadDataContainer('tl_real_estate');

            // Trigger the save_callback
            if (\is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields']['alias']['save_callback']))
            {
                $dc = new \stdClass();
                $dc->id = $objRealEstate->id ?: Database::getInstance()->getNextId('tl_real_estate');
                $dc->table = 'tl_real_estate';
                $dc->activeRecord = null;

                foreach ($GLOBALS['TL_DCA']['tl_real_estate']['fields']['alias']['save_callback'] as $callback)
                {
                    if (\is_array($callback))
                    {
                        $this->import($callback[0]);
                        $objRealEstate->alias = $this->{$callback[0]}->{$callback[1]}($objRealEstate->alias, $dc, $objRealEstate->objekttitel);
                    }
                    elseif (\is_callable($callback))
                    {
                        $objRealEstate->alias = $callback($objRealEstate->alias, $dc, $objRealEstate->objekttitel);
                    }
                }
            }

            // HOOK: before real estate import
            if (isset($GLOBALS['TL_HOOKS']['beforeRealEstateImport']) && \is_array($GLOBALS['TL_HOOKS']['beforeRealEstateImport']))
            {
                foreach ($GLOBALS['TL_HOOKS']['beforeRealEstateImport'] as $callback)
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($objRealEstate, $this);
                }
            }

            $objRealEstate->save();
        }

        if ($this->updateSyncTime)
        {
            $this->objInterface->lastSync = time();
            $this->objInterface->save();
        }

        try {
            $tmpFolder = new Folder($this->objImportFolder->path . '/tmp');
        } catch (\Exception $e) {
            return;
        }

        // Clear tmp folder
        $tmpFolder->purge();

        // Create history entry
        $objInterfaceHistory = new InterfaceHistoryModel();
        $objInterfaceHistory->pid = $this->objInterface->id;
        $objInterfaceHistory->tstamp = time();
        $objInterfaceHistory->source = $this->originalSyncFile;
        $objInterfaceHistory->action = '';
        $objInterfaceHistory->username = $this->username;
        $objInterfaceHistory->text = $this->importMessage;
        $objInterfaceHistory->status = $this->importStatus;
        $objInterfaceHistory->save();

        if(System::getContainer()->getParameter('contao_estatemanager.send_anonymized_data'))
        {
            try{
                $packages = System::getContainer()->getParameter('kernel.packages');
                $version = $packages['contao-estatemanager/core'] ?? '0.0.0';

                $stripKeys = ['alias', 'provider', 'contactPerson', 'anbieternr', 'qrImageSRC', 'logoImageSRC', 'epassSkalaImageSRC', 'panoramaImageSRC', 'mapViewImageSRC', 'exteriorViewImageSRC', 'interiorViewImageSRC', 'planImageSRC', 'imageSRC', 'titleImageSRC', 'objekttitel', 'objektbeschreibung', 'ausstattBeschr', 'lage', 'sonstigeAngaben', 'objektText', 'dreizeiler'];
                $arrRecords = [];

                foreach ($realEstateRecords as $record)
                {
                    $arrRecords[] = array_diff_key($record, array_flip($stripKeys));
                }

                $response = HttpClient::create()->request('POST', 'https://data.contao-estatemanager.com/em/data', [
                    'body' => [
                        'version' => $version,
                        'records' => $arrRecords,
                    ]
                ]);

                $response->getContent(false);
            } catch (TransportExceptionInterface | RedirectionExceptionInterface | ClientExceptionInterface | ServerExceptionInterface $e) {}
        }

        return true;
    }

    /**
     * Loads the xml in a sync file
     *
     * @return boolean
     */
    protected function loadData()
    {
        $data = file_get_contents(TL_ROOT . '/' . $this->syncFile);

        /* FlowFact
        $data = str_replace('<imo:', '<', $data);
        $data = str_replace('</imo:', '</', $data);

        $oi_open_pos = strpos($data, '<openimmo');
        $oi_close_pos = strpos(substr($data, $oi_open_pos), '>');
        $data = substr($data, 0, $oi_open_pos) . '<openimmo>' . substr($data, $oi_close_pos + $oi_open_pos + 1);
        */

        try {
            $this->data = simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Returns a list of syncable files
     *
     * @param boolean $searchForZip
     *
     * @return array
     */
    public function getSyncFiles($searchForZip=true)
    {
        try {
            $folder = new Folder($this->objImportFolder->path);
        } catch (\Exception $e) {
            return array();
        }

        if ($folder->isEmpty())
        {
            return array();
        }

        $arrFiles = array();
        $lasttime = time();

        $syncFiles = FilesHelper::scandirByExt($this->objImportFolder->path, $searchForZip ? array('zip', 'xml', 'data') : array('xml'));

        $arrSynced = array();
        $objHistory = InterfaceHistoryModel::findMultipleBySources($syncFiles);

        if ($objHistory !== null)
        {
            while ($objHistory->next())
            {
                $arrSynced[$objHistory->source] = $objHistory->current();
            }
        }

        foreach ($syncFiles as $i => $file)
        {
            $mtime = FilesHelper::fileModTime($file);
            $size = FilesHelper::fileSizeFormated($file);

            /*if (array_key_exists($file, $arrSynced))
            {
                $syncedMtime = intval($arrSynced[$file]->tstamp);

                if ($syncedMtime > 0) {
                    $mtime = $syncedMtime;
                }
            }
            else
            {
                if ($lasttime > $mtime) {
                    $lasttime = $mtime;
                }
            }*/

            $arrFiles[] = array(
                "file" => $file,
                "time" => $mtime,
                "size" => $size,
                "user" => array_key_exists($file, $arrSynced) ? $arrSynced[$file]->username : null,
                "status" => array_key_exists($file, $arrSynced) ? intval($arrSynced[$file]->status) : 0,
                "synctime" => array_key_exists($file, $arrSynced) ? intval($arrSynced[$file]->tstamp) : null,
                "checked" => false
            );
        }

        usort($arrFiles, function($a, $b) {
            if ($a == $b) return 0;
            return ($a["time"] > $b["time"]) ? -1 : 1;
        });

        return $arrFiles;
    }

    public function getSyncFile($file)
    {
        if (FilesHelper::fileExt($file) === 'ZIP')
        {
            $this->unzipArchive($file);

            $syncFile = FilesHelper::scandirByExt($this->objImportFolder->path . '/tmp', array('xml'));

            if (\count($syncFile) === 0)
            {
                $this->logger->error('No OpenImmo file was found in archive.');
            }

            if (\count($syncFile) > 1)
            {
                $this->logger->error('More than one OpenImmo file was found in the archive. Only one OpenImmo file is allowed per transfer.');
            }

            return $this->getSyncFile($syncFile[0]);
        }

        return $file;
    }

    /**
     * Unpack zip archive by path
     *
     * @param string $path path to zip file
     *
     * @throws \Exception
     */
    public function unzipArchive($path)
    {
        try {
            $tmpFolder = new Folder(FilesHelper::fileDirPath($path) . 'tmp');
        } catch (\Exception $e) {
            return;
        }

        // Clear tmp folder if not empty
        if (!$tmpFolder->isEmpty())
        {
            $tmpFolder->purge();
        }

        $tmpPath = $tmpFolder->__get('value');

        $zip = new ZipReader($path);
        $files = $zip->getFileList();
        $zip->first();

        foreach ($files as $file)
        {
            $content = $zip->unzip();
            $filePath = TL_ROOT . '/' . $tmpPath . '/' . $file;
            $dir = \dirname($filePath);

            if (!file_exists($dir))
            {
                mkdir($dir);
            }

            file_put_contents(TL_ROOT . '/' . $tmpPath . '/' . $file, $content);
            $zip->next();
        }
    }

    protected function getUniqueValue($realEstate)
    {
        // ToDo: Create specific method in model
        $interfaceMappingUniqueField = InterfaceMappingModel::findOneBy(array('pid=? && attribute=?'), array($this->objInterface->id, $this->objInterface->uniqueField));

        $groups = $realEstate->xpath($interfaceMappingUniqueField->oiFieldGroup);

        return $this->getFieldData($interfaceMappingUniqueField->oiField, $groups[0]);
    }

    public function getFieldData($field, $group)
    {
        $attr = false;
        $attr_pos = strrpos($field, '@');

        if ($attr_pos !== false)
        {
            $attr = substr($field, $attr_pos + 1);
            $field = substr($field, 0, $attr_pos);
        }

        $xmlNodes = $field === '' ? array($group) : ($group ? $group->xpath($field) : []);
        $results = array();

        foreach ($xmlNodes as $i => $xmlNode)
        {
            if ($attr)
            {
                $attributes = $xmlNode->attributes();

                switch ($attr)
                {
                    case '*':
                        // Returns a serialized array of all attributes.
                        $results[$i] = serialize(current($attributes));
                        break;
                    case '+':
                        // Returns a single name out of a set of attributes whose value is true.
                        foreach (current($attributes) as $index => $a) {
                            if ($a === 'true' || $a === '1') {
                                $results[$i] = $index;
                            }
                        }
                        break;
                    case '#':
                        // Returns a serialized array of attribute names whose values are true.
                        $tmp = [];
                        if ($attributes->count()) {
                            foreach (current($attributes) as $index => $a) {
                                if ($a === 'true' || $a === '1') {
                                    $tmp[] = $index;
                                }
                            }
                        }
                        if (\count($tmp))
                        {
                            $results[$i] = serialize($tmp);
                        }
                        break;
                    case '[1]':
                        // Returns the first child nodes name.
                        $index = 0;
                        foreach ($xmlNode->children() as $c) {
                            if ($index === 0) {
                                $results[$i] = $c->getName();
                            }
                            $index++;
                        }
                        break;
                    case '[2]':
                        // Returns the first child nodes name.
                        $index = 0;
                        foreach ($xmlNode->children() as $c) {
                            if ($index === 1) {
                                $results[$i] = $c->getName();
                            }
                            $index++;
                        }
                        break;
                    case '[3]':
                        // Returns the first child nodes name.
                        $index = 0;
                        foreach ($xmlNode->children() as $c) {
                            if ($index === 2) {
                                $results[$i] = $c->getName();
                            }
                            $index++;
                        }
                        break;
                    default:
                        // Returns the value of an XML element.
                        $results[$i] = current($attributes)[$attr] ?? null;
                        break;
                }
            }
            else
            {
                if ($xmlNode->attributes()->count())
                {
                    $results[$i] = next($xmlNode);
                }
                else
                {
                    $results[$i] = current($xmlNode);
                }
            }
        }

        if (\count($results) === 1)
        {
            // Trim strings
            if (\is_string($results[0]))
            {
                $results[0] = trim($results[0]);
            }

            return $results[0];
        }
        elseif (\count($results) > 1)
        {
            return serialize($results);
        }

        return null;
    }

    protected function getPath($group, $strPath)
    {
        $fieldPos = strrpos($strPath, '/');
        $field    = substr($strPath, $fieldPos + 1);
        $path     = substr($strPath, 0, $fieldPos);

        return array($group->xpath($path)[0], $field);
    }

    protected function formatValue($value)
    {
        switch ($this->objInterfaceMapping->formatType)
        {
            case 'number':
                $value = number_format(\floatval($value), $this->objInterfaceMapping->decimals, '.', '');

                if ($this->objInterfaceMapping->decimals == 0)
                {
                    $value = intval($value);
                }
                break;
            case 'date':
                $value = strtotime($value);
                break;
            case 'text':
                switch ($this->objInterfaceMapping->textTransform)
                {
                    case 'lowercase';
                        $value = strtolower($value);
                        break;
                    case 'uppercase';
                        $value = strtoupper($value);
                        break;
                    case 'capitalize';
                        $value = ucfirst($value);
                        break;
                    case 'removespecialchar':
                        $value = $this->standardizeSpecialChars($value);
                        break;
                }
                if ($this->objInterfaceMapping->trim)
                {
                    $value = trim($value);
                }
                break;
            case 'boolean':
                if ($this->objInterfaceMapping->booleanCompareValue)
                {
                    if ($this->objInterfaceMapping->booleanCompareValue === $value)
                    {
                        $value = '1';
                    }
                    else
                    {
                        $value = '0';
                    }
                }
                elseif ($value && ($value === '1' || $value === 'true'))
                {
                    $value = '1';
                }
                else
                {
                    $value = '0';
                }
                break;
        }

        return $value;
    }

    protected function standardizeSpecialChars($content)
    {
        // Convert microsoft special characters
        $replace = array(
            "‘" => "'",
            "’" => "'",
            "”" => '"',
            "“" => '"',
            "" => '"',
            "" => '"',
            "–" => "-",
            "—" => "-",
            "" => "-",
            "…" => "&#8230;"
        );

        foreach($replace as $k => $v)
        {
            $content = str_replace($k, $v, $content);
        }

        // Remove any non-ascii character
        // $content = preg_replace('/[^\x20-\x7E]*/','', $content);

        return $content;
    }

    protected function getContactPersonParameters($contactPerson, $objProvider)
    {
        $arrColumns = array('pid=?');
        $arrValues = array($objProvider->id);

        switch ($this->objInterface->contactPersonUniqueField)
        {
            case 'name_vorname':
                $arrColumns[] = 'name=? && vorname=?';
                $arrValues[] = $contactPerson['name'];
                $arrValues[] = $contactPerson['vorname'];
                break;

            default:
                $arrColumns[] = $this->objInterface->contactPersonUniqueField.'=?';
                $arrValues[] = $contactPerson[$this->objInterface->contactPersonUniqueField];
        }

        return array($arrColumns, $arrValues);
    }

    /**
     * Download and save a file
     *
     * @return boolean
     */
    protected function saveImage($interfaceMapping, $tmpGroup, &$value, &$values)
    {
        $skip = false;

        $objFilesFolder = $interfaceMapping->type === 'tl_contact_person' ? $this->objFilesFolderContactPerson : $this->objFilesFolder;

        $check = next($tmpGroup->check);
        $isMd5 = false;

        if (isset($check) && FilesHelper::isValidMd5($check))
        {
            $isMd5 = true;
        }

        // HOOK: add custom logic
        if (isset($GLOBALS['TL_HOOKS']['realEstateImportSaveImage']) && \is_array($GLOBALS['TL_HOOKS']['realEstateImportSaveImage']))
        {
            foreach ($GLOBALS['TL_HOOKS']['realEstateImportSaveImage'] as $callback)
            {
                $this->import($callback[0]);
                $this->{$callback[0]}->{$callback[1]}($objFilesFolder, $value, $tmpGroup, $values, $skip, $this);
            }
        }

        if ($skip)
        {
            return false;
        }

        $fileSize = FilesHelper::fileSize($this->objImportFolder->path . '/tmp/' . $value);
        if ($fileSize > 3000000 || $fileSize === 0)
        {
            return false;
        }

        $filePath = $objFilesFolder->path . '/' . $this->uniqueProviderValue . '/' . ($interfaceMapping->type === 'tl_real_estate' ? $this->uniqueValue . '/' : '') . $value;
        $existingFile = FilesModel::findByPath($filePath);

        if ($existingFile !== null && $isMd5 && $existingFile->hash === $check)
        {
            $values[] = $existingFile->uuid;

            $this->logger->error(($existingFile->hash === $check ? 'Image was skipped because it already exists and does not contain any changes.' : 'Image was skipped because it does not exist.'), [
                'filePath' => $objFilesFolder->path . '/' . $this->uniqueProviderValue . '/' . $this->uniqueValue . '/',
                'fileName' => $value
            ], ImportLogger::LOG_DEV);
        }

        $subDirectory = $interfaceMapping->type === 'tl_real_estate' ? $this->uniqueValue : '';
        $objFile = $this->copyFile($value, $objFilesFolder, $this->uniqueProviderValue, $subDirectory);

        if ($objFile === null)
        {
            return false;
        }

        // Delete file, if hash dont match
        if ($check !== false && $isMd5 && $objFile->hash !== $check)
        {
            $objFile->delete();
            return false;
        }

        $titel = current($tmpGroup->anhangtitel);

        if (\is_string($titel) && $titel !== '')
        {
            $this->logger->info('Image added.', [
                'title' => $titel,
                'fileName' => $value
            ]);

            $meta = array
            (
                'de' => array // ToDo: Consider real estate language
                (
                    'title'   => $titel,
                    'alt'     => $titel,
                    'link'    => '',
                    'caption' => ''
                )
            );

            $objFile->meta = serialize($meta);

            $objFile->save();
        }

        $value = $objFile->uuid;

        return true;
    }

    protected function copyFile($fileName, $objFolder, $providerDirectoryName, $directoryName)
    {
        if (FilesHelper::isWritable($objFolder->path))
        {
            $objFiles = Files::getInstance();

            $filePathProvider = $objFolder->path . '/' . $providerDirectoryName;
            $filePathRecord = $filePathProvider . ($directoryName !== '' ? '/' . $directoryName : '');
            $filePath = $filePathRecord . '/' . $fileName;

            if (!file_exists($this->objImportFolder->path . '/tmp/' . $fileName))
            {
                return null;
            }

            if (!file_exists($filePathProvider))
            {
                mkdir($filePathProvider);
            }

            if (!file_exists($filePathRecord))
            {
                mkdir($filePathRecord);
            }

            $objFiles->copy($this->objImportFolder->path . '/tmp/' . $fileName, $filePath);

            $objFile = Dbafs::addResource($filePath);

            return $objFile;
        }
    }

    protected function deleteRealEstateImages($objRealEstate, $objProvider)
    {
        $deleteFolder = $this->objFilesFolder->path . '/' . $objProvider->anbieternr . '/' . $objRealEstate->{$this->objInterface->uniqueField};

        Files::getInstance()->rrdir($deleteFolder);
        Dbafs::deleteResource($deleteFolder);
    }

    /**
     * Retrieve meta data of a specific database table.
     *
     * string $strTable  Name of the database table
     *
     * @return array
     */
    protected function getTableMetaData($strTable)
    {
        $arrReturn = array();

        $objDatabase = Database::getInstance();
        $arrFields = $objDatabase->listFields($strTable);

        foreach ($arrFields as $key => $meta)
        {
            if (\is_int($key))
            {
                $arrReturn[$meta['name']] = $meta;
            }
        }

        return $arrReturn;
    }
}
