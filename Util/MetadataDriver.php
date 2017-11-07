<?php

namespace TBoileau\RethinkBundle\Util;

use Doctrine\Common\Annotations\Reader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Config\ConfigCache;
use Symfony\Component\Config\Resource\FileResource;

class MetadataDriver
{
    private $annotationReader;

    private $documents;

    public function __construct(Reader $annotationReader, $cacheDir, ProxyGenerator $proxyGenerator)
    {
        $this->annotationReader = $annotationReader;
        $cachePath = $cacheDir.'/rethink_metadata.yml';
        $documentsCache = new ConfigCache($cachePath, true);
        if (!$documentsCache->isFresh()) {
            $finder = new Finder();
            $finder->files()->files()->in(array(
                __DIR__.'/../../../../src/*/Document',
                __DIR__.'/../../../../src/Document',
                __DIR__.'/../../../../src/Documents',
                // __DIR__.'/../../../../src/*/*/Document',
            ));
            foreach($finder as $file){
                $class = str_replace("/","\\",str_replace(".php","",substr($file->getPathname(),strrpos($file->getPathname(),"src/")+4)));
                $reflectionClass = new \ReflectionClass($class);
                if($annotationClass = $this->annotationReader->getClassAnnotation($reflectionClass, 'TBoileau\\RethinkBundle\\ODM\\Metadata\\Table')){
                    $this->documents[$class] = [
                        "class"=>$class,
                        "table"=>(array)$annotationClass,
                        "id"=>[],
                        "properties"=>[
                            "columns"=>[],
                            "manyToOne"=>[],
                            "oneToMany"=>[],
                            "manyToMany"=>[]
                        ]
                    ];
                    foreach ($reflectionClass->getProperties() as $reflectionProperty) {
                        if ($annotationProperty = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'TBoileau\\RethinkBundle\\ODM\\Metadata\\Id')) {
                            $this->documents[$class]["id"] = (array)$annotationProperty;
                        }
                        if ($annotationProperty = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'TBoileau\\RethinkBundle\\ODM\\Metadata\\Column')) {
                            $this->documents[$class]["properties"]["columns"][$reflectionProperty->getName()] = (array)$annotationProperty;
                        }
                        if ($annotationProperty = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'TBoileau\\RethinkBundle\\ODM\\Metadata\\ManyToOne')) {
                            $this->documents[$class]["properties"]["manyToOne"][$reflectionProperty->getName()] = (array)$annotationProperty;
                        }
                        if ($annotationProperty = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'TBoileau\\RethinkBundle\\ODM\\Metadata\\OneToMany')) {
                            $this->documents[$class]["properties"]["oneToMany"][$reflectionProperty->getName()] = (array)$annotationProperty;
                        }
                        if ($annotationProperty = $this->annotationReader->getPropertyAnnotation($reflectionProperty, 'TBoileau\\RethinkBundle\\ODM\\Metadata\\ManyToMany')) {
                            $this->documents[$class]["properties"]["manyToMany"][$reflectionProperty->getName()] = (array)$annotationProperty;
                        }
                    }
                }

            }
            file_put_contents($cachePath, Yaml::dump($this->documents));
        }else{
            $this->documents = Yaml::parse($cachePath);
        }
    }

    public function getDocument($class)
    {
        return $this->documents[$class];
    }

    public function getDocuments()
    {
        return $this->documents;
    }
}
