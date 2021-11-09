<?php

namespace ContaoEstateManager\EstateManager\EventListener\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Contao\System;

/**
 * Callback handler for the table tl_real_estate_config.
 *
 * @author Daniele Sciannimanica <https://github.com/doishub>
 */
class ConfigDcaListener
{
    /**
     * Generate Api key if no own one has been set.
     *
     * @Callback(table="tl_real_estate_config", target="fields.cemApiKey.save")
     */
    public function onSaveApiKey($value, DataContainer $dc)
    {
        if(!trim($value))
        {
            $value = bin2hex(random_bytes(12));
        }

        return $value;
    }

    /**
     * Return options of exception notifications
     *
     * @Callback(table="tl_real_estate_config", target="fields.cemExceptionNotifications.options")
     */
    public function getExceptionNotificationOptions(DataContainer $dc)
    {
        System::loadLanguageFile('tl_real_estate_config');

        $values = [];

        foreach ($GLOBALS['CEM_EEN'] as $exceptions => $strClass)
        {
            $values[ $exceptions ] = $GLOBALS['TL_LANG']['tl_real_estate_config'][ $exceptions ] ?? '';
        }

        return $values;
    }
}
