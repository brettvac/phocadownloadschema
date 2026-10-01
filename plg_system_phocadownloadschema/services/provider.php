<?php
/**
 * @package     Phoca Download Schema Plugin
 * @version     1.0
 * @license     GNU General Public License version 2
 */
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Naftee\Plugin\System\Phocadownloadschema\Extension\Phocadownloadschema;

return new class implements ServiceProviderInterface {
    public function register(Container $container) {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $dispatcher = $container->get(DispatcherInterface::class);
                $plugin = new Phocadownloadschema(
                    $dispatcher,
                    (array) PluginHelper::getPlugin('system', 'phocadownloadschema')
                );
                
                return $plugin;
            }
        );
    }
};