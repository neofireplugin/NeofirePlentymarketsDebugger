<?php declare(strict_types=1);

namespace NeofirePlentymarketsDebugger;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class NeofirePlentymarketsDebugger extends Plugin
{

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);

        $connection->executeUpdate('DELETE FROM `system_config` WHERE `configuration_key` LIKE \'NeofirePlentymarketsDebugger.%\';');

    }


}
