<?php

declare(strict_types = 1);

namespace Sweetchuck\GitHooks\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Sweetchuck\GitHooks\ConfigReader;
use Sweetchuck\GitHooks\GitHookManager;

class Plugin implements PluginInterface, EventSubscriberInterface, Capable
{

    protected Event $event;

    protected Composer $composer;

    protected IOInterface $io;

    protected ConfigReader $configReader;

    protected GitHookManager $gitHookManager;

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'onPostInstallCmd',
            ScriptEvents::POST_UPDATE_CMD => 'onPostUpdateCmd',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getCapabilities()
    {
        return [
            ComposerCommandProvider::class => CommandProvider::class,
        ];
    }

    public function __construct(
        ?ConfigReader $configReader = null,
        ?GitHookManager $deployer = null
    ) {
        $this->configReader = $configReader ?: new ConfigReader();
        $this->gitHookManager = $deployer ?: new GitHookManager();
    }

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        // Nothing to do here, as all features are provided through event listeners.
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {@inheritDoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
        // Nothing to do here, as all features are provided through event listeners.
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * {@inheritDoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        $this->recall();
    }

    public function onPostInstallCmd(Event $event): bool
    {
        return $this->onPostUpdateCmd($event);
    }

    public function onPostUpdateCmd(Event $event): bool
    {
        $this->composer = $event->getComposer();
        $this->io = $event->getIO();

        $result = $this->deploy();

        return $result['exitCode'] === 0;
    }

    protected function deploy(): array
    {
        $config = $this->getConfig();
        $this->gitHookManager->setLogger($this->io);

        return $this->gitHookManager->deploy($config);
    }

    protected function recall(): array
    {
        $config = $this->getConfig();
        $this->gitHookManager->setLogger($this->io);

        return $this->gitHookManager->recall($config);
    }

    protected function getConfig(): array
    {
        $package = $this->composer->getPackage();
        $extra = $package->getExtra();

        return $this
            ->configReader
            ->getConfig(null, $extra[$package->getName()] ?? []);
    }
}
