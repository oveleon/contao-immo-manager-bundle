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

/**
 * Expose module "details".
 *
 * @author Daniele Sciannimanica <daniele@oveleon.de>
 */
class ExposeModuleDetails extends ExposeModule
{
    /**
     * Template
     * @var string
     */
    protected $strTemplate = 'expose_mod_details';

    /**
     * Do not display the module if there are no real etates
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE')
        {
            $objTemplate = new \BackendTemplate('be_wildcard');
            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['details'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=expose_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        return parent::generate();
    }

    /**
     * Generate the module
     */
    protected function compile()
    {
        $arrBlocks = \StringUtil::deserialize($this->detailBlocks);

        $arrCollection = array();

        $seperateBlocks = null;

        if($arrBlocks)
        {
            if(!$this->summariseDetailBlocks)
            {
                $seperateBlocks = $arrBlocks;
            }

            $arrDetails = $this->realEstate->getDetails($seperateBlocks, $this->includeAddress, $arrBlocks);

            // sort by user preference
            if(!$this->summariseDetailBlocks)
            {
                $orderedDetails = array();

                foreach ($arrBlocks as $index)
                {
                    if(array_key_exists($index, $arrDetails))
                    {
                        $orderedDetails[ $index ] = $arrDetails[ $index ];
                    }
                }

                $arrDetails = $orderedDetails;
            }

            // set headline
            foreach ($arrDetails as $block => $details)
            {
                $arrCollection[] = array(
                    'key'     => $block,
                    'label'   => Translator::translateExpose('headline_' . $block),
                    'details' => $details
                );
            }
        }

        // HOOK: custom logic for details
        if (isset($GLOBALS['TL_HOOKS']['compileExposeDetails']) && \is_array($GLOBALS['TL_HOOKS']['compileExposeDetails']))
        {
            foreach ($GLOBALS['TL_HOOKS']['compileExposeDetails'] as $callback)
            {
                $this->import($callback[0]);
                $this->{$callback[0]}->{$callback[1]}($this->Template, $arrCollection, $this);
            }
        }

        $this->Template->details = $arrCollection;
    }
}