<?php

use AppBundle\DependencyInjection\Compiler\PublicForTestsCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;

class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new AppBundle\AppBundle(),
            new \JMS\SerializerBundle\JMSSerializerBundle(),
            new \Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle(),
            new \OldSound\RabbitMqBundle\OldSoundRabbitMqBundle(),
        ];

        if (in_array($this->getEnvironment(), ['dev', 'test'], true)) {
            $bundles[] = new Symfony\Bundle\DebugBundle\DebugBundle();
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
        }

        return $bundles;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return dirname(__DIR__).'/var/cache/'.$this->getEnvironment();
    }

    public function getLogDir()
    {
        return dirname(__DIR__).'/var/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $localFile = $this->getRootDir().'/config/local_'.$this->getEnvironment().'.yml';
        if (is_file($localFile)) {
            $loader->load($localFile);
        } else {
            $loader->load($this->getRootDir().'/config/config_'.$this->getEnvironment().'.yml');
        }
    }

    protected function build(ContainerBuilder $containerBuilder): void
    {
        if (in_array($this->getEnvironment(), ['test'], true)) {
            $containerBuilder->addCompilerPass(new PublicForTestsCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000000);
        }
    }
}
