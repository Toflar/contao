<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\EventListener\DataContainer;

use Contao\CoreBundle\ServiceAnnotation\Callback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;

/**
 * @internal
 *
 * @Callback(table="tl_content", target="fields.type.save")
 * @Callback(table="tl_module", target="fields.type.save")
 */
class ResetCustomTemplateListener
{
    public function __construct(private Connection $connection)
    {
    }

    /**
     * Resets the custom template if the element type changes.
     */
    public function __invoke(mixed $varValue, DataContainer $dc): mixed
    {
        $type = $dc->getCurrentRecord()['type'] ?? null;

        if ($type === $varValue) {
            return $varValue;
        }

        $GLOBALS['TL_DCA'][$dc->table]['config']['onsubmit_callback'][] = function (DataContainer $dc): void {
            if (!$dc->id) {
                return;
            }

            $this->connection->update($dc->table, ['customTpl' => ''], ['id' => $dc->id]);
        };

        return $varValue;
    }
}
