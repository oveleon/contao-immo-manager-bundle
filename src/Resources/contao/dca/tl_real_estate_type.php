<?php
/**
 * This file is part of Contao EstateManager.
 *
 * @link      https://www.contao-estatemanager.com/
 * @source    https://github.com/contao-estatemanager/core
 * @copyright Copyright (c) 2019  Oveleon GbR (https://www.oveleon.de)
 * @license   https://www.contao-estatemanager.com/lizenzbedingungen.html
 */

// load real estate language and filter file
Contao\System::loadLanguageFile('tl_real_estate');
Contao\System::loadLanguageFile('tl_filter');


$GLOBALS['TL_DCA']['tl_real_estate_type'] = array
(

    // Config
    'config' => array
    (
        'dataContainer'               => 'Table',
        'ptable'                      => 'tl_real_estate_group',
        'enableVersioning'            => true,
        'markAsCopy'                  => 'title',
        'onload_callback' => array
        (
            array('tl_real_estate_type', 'checkPermission')
        ),
        'sql' => array
        (
            'keys' => array
            (
                'id' => 'primary',
                'pid,published' => 'index'
            )
        )
    ),

    // List
    'list' => array
    (
        'sorting' => array
        (
            'mode'                    => 4,
            'fields'                  => array('sorting'),
            'headerFields'            => array('title', 'tstamp'),
            'panelLayout'             => 'filter;search,limit',
            'child_record_callback'   => array('tl_real_estate_type', 'stringify')
        ),
        'global_operations' => array
        (
            'all' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'                => 'act=select',
                'class'               => 'header_edit_all',
                'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
            )
        ),
        'operations' => array
        (
            'editheader' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_real_estate_type']['editheader'],
                'href'                => 'act=edit',
                'icon'                => 'edit.svg',
                'button_callback'     => array('tl_real_estate_type', 'editHeader')
            ),
            'copy' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_real_estate_type']['copy'],
                'href'                => 'act=paste&amp;mode=copy',
                'icon'                => 'copy.svg'
            ),
            'cut' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_real_estate_type']['cut'],
                'href'                => 'act=paste&amp;mode=cut',
                'icon'                => 'cut.svg'
            ),
            'delete' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_real_estate_type']['delete'],
                'href'                => 'act=delete',
                'icon'                => 'delete.svg',
                'attributes'          => 'onclick="if(!confirm(\'' . ($GLOBALS['TL_LANG']['MSC']['deleteConfirm'] ?? null) . '\'))return false;Backend.getScrollOffset()"'
            ),
            'toggle' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_real_estate_type']['toggle'],
                'icon'                => 'visible.svg',
                'attributes'          => 'onclick="Backend.getScrollOffset();return AjaxRequest.toggleVisibility(this,%s,\'tl_real_estate_type\')"',
                'button_callback'     => array('tl_real_estate_type', 'toggleIcon')
            ),
            'show' => array
            (
                'label'               => &$GLOBALS['TL_LANG']['tl_real_estate_type']['show'],
                'href'                => 'act=show',
                'icon'                => 'show.svg'
            )
        )
    ),

    // Palettes
    'palettes' => array
    (
        '__selector__'                => array('excludeTypes', 'orderFields'),
        'default'                     => '{title_legend},title,longTitle,similarType;{forwarding_legend},referencePage,jumpTo;{field_legend},nutzungsart,vermarktungsart,objektart,excludeTypes;{filter_legend},price,area,toggleFilter,sortingOptions;{display_legend},mainDetails,mainAttributes,orderFields;{language_legend},language;{publish_legend},defaultType,published'
    ),

    // Subpalettes
    'subpalettes' => array
    (
        'excludeTypes'                 => 'excludedTypes',
        'orderFields'                  => 'orderedFields'
    ),

    // Fields
    'fields' => array
    (

        'id' => array
        (
            'sql'                     => "int(10) unsigned NOT NULL auto_increment"
        ),
        'pid' => array
        (
            'foreignKey'              => 'tl_real_estate_group.title',
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
            'relation'                => array('type'=>'belongsTo', 'load'=>'eager')
        ),
        'sorting' => array
        (
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ),
        'tstamp' => array
        (
            'sql'                     => "int(10) unsigned NOT NULL default '0'"
        ),
        'title' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['title'],
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'longTitle' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['longTitle'],
            'exclude'                 => true,
            'search'                  => true,
            'inputType'               => 'text',
            'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
            'sql'                     => "varchar(255) NOT NULL default ''"
        ),
        'similarType' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['similarType'],
            'exclude'                 => true,
            'inputType'               => 'select',
            'options_callback'        => array('tl_real_estate_type', 'getRealEstateTypes'),
            'foreignKey'              => 'tl_real_estate_type.title',
            'eval'                    => array('includeBlankOption'=>true, 'tl_class'=>'w50'),
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
            'relation'                => array('type'=>'hasOne', 'load'=>'lazy')
        ),
        'referencePage' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['referencePage'],
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'foreignKey'              => 'tl_page.title',
            'eval'                    => array('fieldType'=>'radio', 'tl_class'=>'w50'),
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
            'relation'                => array('type'=>'hasOne', 'load'=>'lazy')
        ),
        'jumpTo' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['jumpTo'],
            'exclude'                 => true,
            'inputType'               => 'pageTree',
            'foreignKey'              => 'tl_page.title',
            'eval'                    => array('fieldType'=>'radio', 'tl_class'=>'w50'),
            'sql'                     => "int(10) unsigned NOT NULL default '0'",
            'relation'                => array('type'=>'hasOne', 'load'=>'lazy')
        ),
        'nutzungsart' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['nutzungsart'],
            'exclude'                 => true,
            'inputType'               => 'select',
            'options'                 => array
            (
                'wohnen'    => &$GLOBALS['TL_LANG']['tl_real_estate_type']['nutzungsart_wohnen'],
                'gewerbe'   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['nutzungsart_gewerbe'],
                'anlage'    => &$GLOBALS['TL_LANG']['tl_real_estate_type']['nutzungsart_anlage'],
                'waz'       => &$GLOBALS['TL_LANG']['tl_real_estate_type']['nutzungsart_waz']
            ),
            'eval'                    => array('includeBlankOption'=>true, 'tl_class'=>'w50'),
            'sql'                     => "varchar(32) NOT NULL default ''"
        ),
        'vermarktungsart' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['vermarktungsart'],
            'exclude'                 => true,
            'inputType'               => 'select',
            'options'                 => array('kauf_erbpacht', 'miete_leasing'),
            'reference'               => &$GLOBALS['TL_LANG']['tl_real_estate_type'],
            'eval'                    => array('includeBlankOption'=>true, 'mandatory'=>true, 'tl_class'=>'w50'),
            'sql'                     => "varchar(32) NOT NULL default ''",
        ),
        'objektart' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart'],
            'exclude'                 => true,
            'inputType'               => 'select',
            'filter'                  => true,
            'options'                 => array
            (
                'zimmer'                        => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_zimmer'],
                'wohnung'                       => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_wohnung'],
                'haus'                          => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_haus'],
                'grundstueck'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_grundstueck'],
                'buero_praxen'                  => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_buero_praxen'],
                'einzelhandel'                  => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_einzelhandel'],
                'gastgewerbe'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_gastgewerbe'],
                'hallen_lager_prod'             => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_hallen_lager_prod'],
                'land_und_forstwirtschaft'      => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_land_und_forstwirtschaft'],
                'parken'                        => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_parken'],
                'sonstige'                      => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_sonstige'],
                'freizeitimmobilie_gewerblich'  => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_freizeitimmobilie_gewerblich'],
                'zinshaus_renditeobjekt'        => &$GLOBALS['TL_LANG']['tl_real_estate_type']['objektart_zinshaus_renditeobjekt']
            ),
            'reference'               => &$GLOBALS['TL_LANG']['tl_real_estate_type'],
            'eval'                    => array('includeBlankOption'=>true, 'tl_class'=>'w50 clr'),
            'sql'                     => "varchar(32) NOT NULL default ''",
        ),
        'excludeTypes' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['excludeTypes'],
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => array('submitOnChange'=>true, 'doNotCopy'=>true, 'tl_class'=>'clr m12'),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
        'excludedTypes'  => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['excludedTypes'],
            'exclude'                 => true,
            'inputType' 	          => 'multiColumnWizard',
            'eval' 			          => array
            (
                'columnFields' => array
                (
                    'type' => array
                    (
                        'label'             => &$GLOBALS['TL_LANG']['tl_real_estate_type']['excludedTypes'],
                        'exclude'           => true,
                        'inputType'         => 'select',
                        'options_callback'  => array('tl_real_estate_type', 'getFirstLevelTypes'),
                        'eval' 		        => array('style'=>'width:100%', 'chosen'=>true)
                    )
                )
            ),
            'sql'                     => "blob NULL"
        ),
        'price' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['price'],
            'exclude'                 => true,
            'inputType'               => 'select',
            'options_callback'        => array('tl_real_estate_type', 'getPriceFields'),
            'eval'                    => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
            'sql'                     => "varchar(32) NOT NULL default ''"
        ),
        'area' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['area'],
            'exclude'                 => true,
            'inputType'               => 'select',
            'options_callback'        => array('tl_real_estate_type', 'getAreaFields'),
            'eval'                    => array('includeBlankOption'=>true, 'chosen'=>true, 'tl_class'=>'w50'),
            'sql'                     => "varchar(32) NOT NULL default ''"
        ),
        'toggleFilter'  => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['toggleFilter'],
            'exclude'                 => true,
            'inputType'               => 'checkboxWizard',
            'options'                 => array('price', 'per', 'room', 'area', 'period'),
            'reference'               => &$GLOBALS['TL_LANG']['tl_filter'],
            'eval'                    => array('multiple'=>true, 'tl_class'=>'clr'),
            'sql'                     => "varchar(255) NOT NULL default ''",
        ),
        'sortingOptions'  => array
        (

            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['sortingOptions'],
            'exclude'                 => true,
            'inputType' 	          => 'multiColumnWizard',
            'eval' 			          => array
            (
                'columnFields' => array
                (
                    'field' => array
                    (
                        'label'             => &$GLOBALS['TL_LANG']['tl_real_estate_type']['field'],
                        'exclude'           => true,
                        'inputType'         => 'select',
                        'options_callback'  => array('tl_real_estate_type', 'getSortingFields'),
                        'eval' 		        => array('includeBlankOption'=>true, 'chosen'=>true, 'style'=>'width:100%', 'chosen'=>true)
                    )
                )
            ),
            'sql'                     => "blob NULL"
        ),
        'mainDetails'  => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['mainDetails'],
            'exclude'                 => true,
            'inputType' 	          => 'multiColumnWizard',
            'eval' 			          => array
            (
                'dragAndDrop'  => true,
                'columnFields' => array
                (
                    'field' => array
                    (
                        'label'       => &$GLOBALS['TL_LANG']['tl_real_estate_type']['mainDetails'],
                        'exclude'     => true,
                        'inputType'   => 'select',
                        'options_callback'  => array('tl_real_estate_type', 'getMixedDetailsFields'),
                        'eval' 		  => array('includeBlankOption'=>true, 'style'=>'width:100%', 'chosen'=>true)
                    )
                )
            ),
            'sql'                     => "blob NULL"
        ),
        'mainAttributes'  => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['mainAttributes'],
            'exclude'                 => true,
            'inputType' 	          => 'multiColumnWizard',
            'eval' 			          => array
            (
                'dragAndDrop'  => true,
                'columnFields' => array
                (
                    'field' => array
                    (
                        'label'       => &$GLOBALS['TL_LANG']['tl_real_estate_type']['mainAttributes'],
                        'exclude'     => true,
                        'inputType'   => 'select',
                        'options_callback'  => array('tl_real_estate_type', 'getAttributeFields'),
                        'eval' 		  => array('includeBlankOption'=>true, 'style'=>'width:100%', 'chosen'=>true)
                    )
                )
            ),
            'sql'                     => "blob NULL"
        ),
        'orderFields' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['orderFields'],
            'exclude'                 => true,
            'inputType'               => 'checkbox',
            'eval'                    => array('submitOnChange'=>true, 'doNotCopy'=>true, 'tl_class'=>'clr m12'),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
        'orderedFields'  => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['orderedFields'],
            'exclude'                 => true,
            'inputType' 	          => 'multiColumnWizard',
            'eval' 			          => array
            (
                'dragAndDrop'  => true,
                'columnFields' => array
                (
                    'field' => array
                    (
                        'label'             => &$GLOBALS['TL_LANG']['tl_real_estate_type']['field'],
                        'inputType'         => 'select',
                        'options_callback'  => array('tl_real_estate_type', 'getRealEstateColumns'),
                        'eval' 		        => array('includeBlankOption'=>true, 'style'=>'width:100%', 'chosen'=>true)
                    )
                )
            ),
            'sql'                     => "blob NULL"
        ),
        'language' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['language'],
            'exclude'                 => true,
            'inputType'               => 'text',
            'eval'                    => array('rgxp'=>'language', 'maxlength'=>5, 'nospace'=>true, 'doNotCopy'=>true, 'tl_class'=>'w50'),
            'sql'                     => "varchar(5) NOT NULL default ''"
        ),
        'defaultType' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['defaultType'],
            'exclude'                 => true,
            'flag'                    => 1,
            'inputType'               => 'checkbox',
            'eval'                    => array('doNotCopy'=>true),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
        'published' => array
        (
            'label'                   => &$GLOBALS['TL_LANG']['tl_real_estate_type']['published'],
            'exclude'                 => true,
            'filter'                  => true,
            'flag'                    => 1,
            'inputType'               => 'checkbox',
            'eval'                    => array('doNotCopy'=>true),
            'sql'                     => "char(1) NOT NULL default ''"
        ),
    )
);


/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Fabian Ekert <https://github.com/eki89>
 * @author Daniele Sciannimanica <https://github.com/doishub>
 */
class tl_real_estate_type extends Contao\Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('Contao\BackendUser', 'User');

        $this->loadDataContainer('tl_real_estate');
    }

    /**
     * Check permissions to edit table tl_real_estate_type
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function checkPermission(): void
    {
        if ($this->User->isAdmin)
        {
            return;
        }

        // Set root IDs
        if (empty($this->User->regroups) || !is_array($this->User->regroups))
        {
            $root = array(0);
        }
        else
        {
            $root = $this->User->regroups;
        }

        $id = strlen(Contao\Input::get('id')) ? Contao\Input::get('id') : CURRENT_ID;

        // Check current action
        switch (Contao\Input::get('act'))
        {
            case 'paste':
            case 'select':
                // Check CURRENT_ID here (see #247)
                if (!in_array(CURRENT_ID, $root))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to access real estate group ID ' . $id . '.');
                }
                break;

            case 'create':
                if (!Contao\Input::get('pid') || !in_array(Contao\Input::get('pid'), $root))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to create type in real estate group ID ' . Contao\Input::get('pid') . '.');
                }
                break;

            case 'cut':
            case 'copy':
                if (!in_array(Contao\Input::get('pid'), $root))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' real estate type ID ' . $id . ' to real estate group ID ' . Contao\Input::get('pid') . '.');
                }
            // no break

            case 'edit':
            case 'show':
            case 'delete':
            case 'toggle':
                $objRealEstateType = $this->Database->prepare("SELECT pid FROM tl_real_estate_type WHERE id=?")
                    ->limit(1)
                    ->execute($id);

                if ($objRealEstateType->numRows < 1)
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Invalid real estate type ID ' . $id . '.');
                }

                if (!in_array($objRealEstateType->pid, $root))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to ' . Contao\Input::get('act') . ' real estate type ID ' . $id . ' of real estate group ID ' . $objRealEstateType->pid . '.');
                }
                break;

            case 'editAll':
            case 'deleteAll':
            case 'overrideAll':
            case 'cutAll':
            case 'copyAll':
                if (!in_array($id, $root))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to access real estate type ID ' . $id . '.');
                }

                $objRealEstateType = $this->Database->prepare("SELECT id FROM tl_real_estate_type WHERE pid=?")
                    ->execute($id);

                /** @var Symfony\Component\HttpFoundation\Session\SessionInterface $objSession */
                $objSession = Contao\System::getContainer()->get('session');

                $session = $objSession->all();
                $session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $objRealEstateType->fetchEach('id'));
                $objSession->replace($session);
                break;

            default:
                if (Contao\Input::get('act'))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Invalid command "' . Contao\Input::get('act') . '".');
                }

                if (!in_array($id, $root))
                {
                    throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to access real estate type ID ' . $id . '.');
                }
                break;
        }
    }

    /**
     * Stringify real estate type und return it as string
     *
     * @param array $arrRow
     *
     * @return string
     */
    public function stringify(array $arrRow): string
    {
        return '<div class="tl_content_left">' . $arrRow['title'] . ' <span style="color:#999;padding-left:3px">(' . $arrRow['longTitle'] . ')</span></div>';
    }

    /**
     * Return the edit header button
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function editHeader(array $row, string $href, string $label, string $title, string $icon, string $attributes): string
    {
        return $this->User->canEditFieldsOf('tl_real_estate_type') ? '<a href="'.$this->addToUrl($href.'&amp;id='.$row['id']).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label).'</a> ' : Contao\Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)).' ';
    }


    /**
     * Return all fields from real estate dca as array
     *
     * @return array
     */
    public function getFirstLevelTypes(): array
    {
        $objTypes = $this->Database->execute("SELECT id, title, longTitle FROM tl_real_estate_type WHERE excludeTypes!=1");

        if ($objTypes->numRows < 1)
        {
            return array();
        }

        $arrTypes = array();

        while ($objTypes->next())
        {
            $arrTypes[$objTypes->id] = $objTypes->title . ' (' . $objTypes->longTitle . ')';
        }

        return $arrTypes;
    }

    /**
     * Return all real estate types as array
     *
     * @param Contao\DataContainer $dc
     *
     * @return array
     */
    public function getRealEstateTypes(Contao\DataContainer $dc): array
    {
        $objTypes = $this->Database->prepare("SELECT id, title, longTitle FROM tl_real_estate_type WHERE id!=?")
            ->execute($dc->activeRecord->id);

        if ($objTypes->numRows < 1)
        {
            return array();
        }

        $arrTypes = array();

        while ($objTypes->next())
        {
            $arrTypes[$objTypes->id] = $objTypes->title . ' (' . $objTypes->longTitle . ')';
        }

        return $arrTypes;
    }

    /**
     * Return all price fields from real estate dca as array
     *
     * @return array
     */
    public function getPriceFields(): array
    {
        $priceFields = array();

        if (is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate']['fields'] as $field => $data)
            {
                if($data['realEstate']['price'] ?? null)
                {
                    $priceFields[$field] = $GLOBALS['TL_LANG']['tl_real_estate'][$field][0] . ' [' . $field . ']';
                }
            }
        }

        return $priceFields;
    }

    /**
     * Return all area fields from real estate dca as array
     *
     * @return array
     */
    public function getAreaFields(): array
    {
        $areaFields = array();

        if (is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate']['fields'] as $field => $data)
            {
                if($data['realEstate']['area'] ?? null)
                {
                    $areaFields[$field] = $GLOBALS['TL_LANG']['tl_real_estate'][$field][0] . ' [' . $field . ']';
                }
            }
        }

        return $areaFields;
    }

    /**
     * Return all attributes from real estate dca as array
     *
     * @return array
     */
    public function getAttributeFields(): array
    {
        $filterFields = array();

        if (is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate']['fields'] as $field => $data)
            {
                if($data['realEstate']['attribute'] ?? null)
                {
                    $filterFields[$field] = $GLOBALS['TL_LANG']['tl_real_estate'][$field][0] . ' [' . $field . ']';
                }
            }
        }

        return $filterFields;
    }

    /**
     * Return all details from real estate dca as array
     *
     * @return array
     */
    public function getMixedDetailsFields(): array
    {
        $filterFields = array();

        if (is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate']['fields'] as $field => $data)
            {
                if($data['realEstate'] ?? null && ($data['realEstate']['detail'] ?? null || $data['realEstate']['price'] ?? null || $data['realEstate']['area'] ?? null))
                {
                    $filterFields[$field] = $GLOBALS['TL_LANG']['tl_real_estate'][$field][0] . ' [' . $field . ']';
                }
            }
        }

        return $filterFields;
    }

    /**
     * Get fields from real estate dca
     *
     * @return array
     */
    public function getRealEstateColumns(){
        $collumns      = array();
        $skipFields    = array('id', 'alias', 'published', 'titleImageSRC', 'imageSRC', 'planImageSRC', 'interiorViewImageSRC', 'exteriorViewImageSRC', 'mapViewImageSRC', 'panormaImageSRC', 'epassSkalaImageSRC', 'logoImageSRC', 'qrImageSRC', 'documents', 'links');

        if (is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields'] ?? null))
        {
            foreach (array_keys($GLOBALS['TL_DCA']['tl_real_estate']['fields']) as $field)
            {
                if (!in_array($field, $skipFields))
                {
                    $collumns[$field] = $GLOBALS['TL_LANG']['tl_real_estate'][$field][0] . ' [' . $field . ']';
                }
            }
        }

        return $collumns;
    }

    /**
     * Return all sorting fields from real estate dca as array
     *
     * @return array
     */
    public function getSortingFields(): array
    {
        $sortingFields = array();

        if (is_array($GLOBALS['TL_DCA']['tl_real_estate']['fields'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate']['fields'] as $field => $data)
            {
                if($data['realEstate']['sorting'] ?? null)
                {
                    $sortingFields[$field] = $GLOBALS['TL_LANG']['tl_real_estate'][$field][0] . ' [' . $field . ']';
                }
            }
        }

        return $sortingFields;
    }

    /**
     * Return the "toggle visibility" button
     *
     * @param array  $row
     * @param string $href
     * @param string $label
     * @param string $title
     * @param string $icon
     * @param string $attributes
     *
     * @return string
     */
    public function toggleIcon(array $row, ?string $href, string $label, string $title, string $icon, string $attributes): string
    {
        if (strlen(Contao\Input::get('tid')))
        {
            $this->toggleVisibility(Contao\Input::get('tid'), (Contao\Input::get('state') == 1), (@func_get_arg(12) ?: null));
            $this->redirect($this->getReferer());
        }

        // Check permissions AFTER checking the tid, so hacking attempts are logged
        if (!$this->User->hasAccess('tl_real_estate_type::published', 'alexf'))
        {
            return '';
        }

        $href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

        if (!$row['published'])
        {
            $icon = 'invisible.svg';
        }

        return '<a href="'.$this->addToUrl($href).'" title="'.Contao\StringUtil::specialchars($title).'"'.$attributes.'>'.Contao\Image::getHtml($icon, $label, 'data-state="' . ($row['published'] ? 1 : 0) . '"').'</a> ';
    }

    /**
     * Toggle the visibility of a format definition
     *
     * @param integer              $intId
     * @param boolean              $blnVisible
     * @param Contao\DataContainer $dc
     */
    public function toggleVisibility(int $intId, bool $blnVisible, Contao\DataContainer $dc=null): void
    {
        // Set the ID and action
        Contao\Input::setGet('id', $intId);
        Contao\Input::setGet('act', 'toggle');

        if ($dc)
        {
            $dc->id = $intId; // see #8043
        }

        // Trigger the onload_callback
        if (is_array($GLOBALS['TL_DCA']['tl_real_estate_type']['config']['onload_callback'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate_type']['config']['onload_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        // Check the field access
        if (!$this->User->hasAccess('tl_real_estate_type::published', 'alexf'))
        {
            throw new Contao\CoreBundle\Exception\AccessDeniedException('Not enough permissions to publish/unpublish filter ID ' . $intId . '.');
        }

        // Set the current record
        if ($dc)
        {
            $objRow = $this->Database->prepare("SELECT * FROM tl_real_estate_type WHERE id=?")
                ->limit(1)
                ->execute($intId);

            if ($objRow->numRows)
            {
                $dc->activeRecord = $objRow;
            }
        }

        $objVersions = new Contao\Versions('tl_real_estate_type', $intId);
        $objVersions->initialize();

        $time = time();

        // Update the database
        $this->Database->prepare("UPDATE tl_real_estate_type SET tstamp=$time, published='" . ($blnVisible ? '1' : '') . "' WHERE id=?")
            ->execute($intId);

        if ($dc)
        {
            $dc->activeRecord->tstamp = $time;
            $dc->activeRecord->published = ($blnVisible ? '1' : '');
        }

        // Trigger the onsubmit_callback
        if (is_array($GLOBALS['TL_DCA']['tl_real_estate_type']['config']['onsubmit_callback'] ?? null))
        {
            foreach ($GLOBALS['TL_DCA']['tl_real_estate_type']['config']['onsubmit_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $this->{$callback[0]}->{$callback[1]}($dc);
                }
                elseif (is_callable($callback))
                {
                    $callback($dc);
                }
            }
        }

        $objVersions->create();
    }
}
