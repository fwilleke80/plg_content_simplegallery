<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.Simplegallery
 *
 * @copyright   (C) 2026
 * @license     GNU General Public License version 2 or later
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Content\Simplegallery\Extension\Simplegallery;

return new class () implements ServiceProviderInterface
{
	/**
	 * Registers the plugin in the DI container.
	 *
	 * @param[in] Container $container The DI container.
	 *
	 * @return void
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container): PluginInterface
			{
				$config = (array) PluginHelper::getPlugin('content', 'simplegallery');
				$dispatcher = $container->get(DispatcherInterface::class);
				$application = Factory::getApplication();

				$plugin = new Simplegallery($dispatcher, $config);
				$plugin->setApplication($application);

				return $plugin;
			}
		);
	}
};