<?php

declare(strict_types=1);

namespace Doctrine\Common\DataFixtures;

use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function get_class;
use function is_array;
use function is_object;
use function method_exists;
use function serialize;
use function substr;
use function unserialize;

/**
 * Proxy reference repository
 *
 * Allow data fixture references and identities to be persisted when cached data fixtures
 * are pre-loaded, for example, by LiipFunctionalTestBundle\Test\WebTestCase loadFixtures().
 */
class ProxyReferenceRepository extends ReferenceRepository
{
    /**
     * Get real class name of a reference that could be a proxy
     *
     * @param string $className Class name of reference object
     *
     * @return string
     */
    protected function getRealClass($className)
    {
        if (substr($className, -5) === 'Proxy') {
            return substr($className, 0, -5);
        }

        return $className;
    }

    /**
     * Serialize reference repository
     *
     * @return string
     */
    public function serialize()
    {
        $unitOfWork       = $this->getManager()->getUnitOfWork();
        $simpleReferences = [];

        foreach ($this->getReferences() as $name => $reference) {
            $className = $this->getRealClass(get_class($reference));
            $id        = $this->serializeIdentifiers(
                $this->getIdentifier($reference, $unitOfWork)
            );

            $simpleReferences[$name] = [$className, $id];
        }

        return serialize([
            'references' => $simpleReferences,
            'identities' => $this->getIdentities(),
        ]);
    }

    /**
     * For ids that are stored in custom value objects we also need to include the className into the result
     * so that when we unserialize we can re-create the right objects.
     *
     * @param array $identifiers
     *
     * @return array
     */
    private function serializeIdentifiers($identifiers)
    {
        foreach ($identifiers as &$id) {
            if (! is_object($id) || ! method_exists($id, 'getId')) {
                continue;
            }

            $id = [
                $this->getRealClass(get_class($id)),
                $id->getId(),
            ];
        }

        return $identifiers;
    }

    /**
     * Unserialize reference repository
     *
     * @param string $serializedData Serialized data
     */
    public function unserialize($serializedData)
    {
        $repositoryData = unserialize($serializedData);
        $references     = $repositoryData['references'];

        foreach ($references as $name => $proxyReference) {
            $className  = $proxyReference[0];
            $identifier = $this->unserializeIdentifiers($proxyReference[1]);

            $this->setReference(
                $name,
                $this->getManager()->getReference(
                    $className,
                    $identifier
                )
            );
        }

        $identities = $repositoryData['identities'];

        foreach ($identities as $name => $identity) {
            $this->setReferenceIdentity($name, $identity);
        }
    }

    /**
     * Few ids have a custom id value object to hold the value, this method will correctly unserialize the id
     * into an instance whenever a custom class name is provided in the data.
     *
     * @param array $identifiers
     *
     * @return array
     */
    private function unserializeIdentifiers($identifiers)
    {
        foreach ($identifiers as &$id) {
            if (! is_array($id)) {
                continue;
            }

            [$className, $id] = $id;
            $id               = new $className($id);
        }

        return $identifiers;
    }

    /**
     * Load data fixture reference repository
     *
     * @param string $baseCacheName Base cache name
     *
     * @return bool
     */
    public function load($baseCacheName)
    {
        $filename = $baseCacheName . '.ser';

        if (! file_exists($filename)) {
            return false;
        }

        $serializedData = file_get_contents($filename);

        if ($serializedData === false) {
            return false;
        }

        $this->unserialize($serializedData);

        return true;
    }

    /**
     * Save data fixture reference repository
     *
     * @param string $baseCacheName Base cache name
     */
    public function save($baseCacheName)
    {
        $serializedData = $this->serialize();

        file_put_contents($baseCacheName . '.ser', $serializedData);
    }
}
