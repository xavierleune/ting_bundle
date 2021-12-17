<?php

namespace CCMBenchmark\TingBundle\Cache;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

class MetadataClearer implements CacheClearerInterface
{
    /**
     * @var Filesystem
     */
    private $fileSystem;

    /** @var string */
    private $cacheFile;

    public function __construct(string $cacheFile, Filesystem $filesystem = null)
    {
        $this->cacheFile = $cacheFile;
        $this->fileSystem = $filesystem ?: new Filesystem();
    }

    public function clear($cacheDir)
    {
        $this->fileSystem->remove($cacheDir . '/' . $this->cacheFile);
    }
}
