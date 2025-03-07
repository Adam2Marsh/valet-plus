<?php

namespace Valet;

class DnsMasq
{
    public $brew;
    public $cli;
    public $files;

    public $resolverPath = '/etc/resolver';
    public $configPath = 'etc/dnsmasq.conf';
    public $exampleConfigPath = 'var/dnsmasq.conf.default';

    /**
     * Create a new DnsMasq instance.
     *
     * @param  Brew $brew
     * @param  CommandLine $cli
     * @param  Filesystem $files
     */
    public function __construct(Brew $brew, CommandLine $cli, Filesystem $files)
    {
        $this->cli = $cli;
        $this->brew = $brew;
        $this->files = $files;
    }

    /**
     * Install and configure DnsMasq.
     *
     * @return void
     */
    public function install($domain = 'test')
    {
        $this->brew->ensureInstalled('dnsmasq');

        // For DnsMasq, we create our own custom configuration file which will be imported
        // in the main DnsMasq file. This allows Valet to make changes to our own files
        // without needing to modify the "primary" DnsMasq configuration files again.
        $this->createCustomConfigFile($domain);

        $this->createDomainResolver($domain);

        $this->brew->restartService('dnsmasq');
    }

    /**
     * Append the custom DnsMasq configuration file to the main configuration file.
     *
     * @param  string  $domain
     * @return void
     */
    public function createCustomConfigFile($domain)
    {
        $customConfigPath = $this->customConfigPath();

        $this->copyExampleConfig();

        $this->appendCustomConfigImport($customConfigPath);

        $this->files->putAsUser($customConfigPath, 'address=/.'.$domain.'/127.0.0.1'.PHP_EOL);
    }

    /**
     * Copy the Homebrew installed example DnsMasq configuration file.
     *
     * @return void
     */
    public function copyExampleConfig()
    {
        if (! $this->files->exists(BREW_PATH . "/" . $this->configPath)) {
            $this->files->copyAsUser(
                BREW_PATH . "/" . $this->exampleConfigPath,
                BREW_PATH . "/" . $this->configPath
            );
        }
    }

    /**
     * Append import command for our custom configuration to DnsMasq file.
     *
     * @param  string  $customConfigPath
     * @return void
     */
    public function appendCustomConfigImport($customConfigPath)
    {
        if (! $this->customConfigIsBeingImported($customConfigPath)) {
            $this->files->appendAsUser(
                BREW_PATH . "/" . $this->configPath,
                PHP_EOL.'conf-file='.$customConfigPath.PHP_EOL
            );
        }
    }

    /**
     * Determine if Valet's custom DnsMasq configuration is being imported.
     *
     * @param  string  $customConfigPath
     * @return bool
     */
    public function customConfigIsBeingImported($customConfigPath)
    {
        return strpos($this->files->get(BREW_PATH . "/" . $this->configPath), $customConfigPath) !== false;
    }

    /**
     * Create the resolver file to point the "test" domain to 127.0.0.1.
     *
     * @param  string  $domain
     * @return void
     */
    public function createDomainResolver($domain)
    {
        $this->files->ensureDirExists($this->resolverPath);

        $this->files->put($this->resolverPath.'/'.$domain, 'nameserver 127.0.0.1'.PHP_EOL);
    }

    /**
     * Update the domain used by DnsMasq.
     *
     * @param  string  $oldDomain
     * @param  string  $newDomain
     * @return void
     */
    public function updateDomain($oldDomain, $newDomain)
    {
        $this->files->unlink($this->resolverPath.'/'.$oldDomain);

        $this->install($newDomain);
    }

    /**
     * Get the custom configuration path.
     *
     * @return string
     */
    public function customConfigPath()
    {
        return $_SERVER['HOME'].'/.valet/dnsmasq.conf';
    }

    /**
     * Start the service.
     *
     * @return void
     */
    public function restart()
    {
        $this->brew->restartService('dnsmasq');
    }

    /**
     * Stop the service.
     *
     * @return void
     */
    public function stop()
    {
        $this->brew->stopService('dnsmasq');
    }
}
