<?php

namespace Sweetchuck\GitHooks\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\ConsoleIO;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Sweetchuck\GitHooks\DeployConfigReader;
use Sweetchuck\GitHooks\Deployer;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{

    /**
     * @var \Composer\Script\Event
     */
    protected $event;

    /**
     * @var \Composer\Composer
     */
    protected $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    protected $io;

    /**
     * @var \Sweetchuck\GitHooks\DeployConfigReader
     */
    protected $deployConfigReader;

    /**
     * @var \Sweetchuck\GitHooks\Deployer
     */
    protected $deployer;

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallCmd',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdateCmd',
        ];
    }

    public function __construct(
        ?DeployConfigReader $deployConfigReader = null,
        ?Deployer $deployer = null
    ) {
        $this->deployConfigReader = $deployConfigReader ?: new DeployConfigReader();
        $this->deployer = $deployer ?: new Deployer();
    }

    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // Nothing to do here, as all features are provided through event listeners.
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * @inheritDoc
     */
    public function getCapabilities()
    {
        return [
            ComposerCommandProvider::class => CommandProvider::class,
        ];
    }

    public function onPostInstallCmd(Event $event): bool
    {
        $result = $this->deploy($event);

        return $result['exitCode'] === 0;
    }

    public function onPostUpdateCmd(Event $event): bool
    {
        $result = $this->deploy($event);

        return $result['exitCode'] === 0;
    }

    protected function deploy(Event $event): array
    {
        $package = $event->getComposer()->getPackage();
        $extra = $package->getExtra();
        $config = $this->deployConfigReader->getConfig(null, $extra[$package->getName()] ?? []);
        /** @var \Composer\IO\ConsoleIO $io */
        $io = $event->getIO();
        $this->deployer->setLogger($io);

        return $this->deployer->deploy($config);
    }
}
