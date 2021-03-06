<?php

declare(strict_types=1);

namespace spaceonfire\Bridge\Cycle;

use Cycle\Database\Config\DatabaseConfig;
use Cycle\Database\DatabaseManager;
use Cycle\Database\DatabaseProviderInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Schema;
use Cycle\Schema\Generator;
use PhpOption\Some;
use PHPUnit\Framework\TestCase;
use spaceonfire\Bridge\Cycle\Factory\OrmFactory;
use spaceonfire\Bridge\Cycle\Factory\SpiralFactory;
use spaceonfire\Bridge\Cycle\Fixtures\OrmCapsule;
use spaceonfire\Bridge\Cycle\Fixtures\PluginsProvider;
use spaceonfire\Bridge\Cycle\Schema\ArraySchemaRegistryFactory;
use spaceonfire\Container\CompositeContainer;
use spaceonfire\Container\DefinitionContainer;
use spaceonfire\Container\Factory\Reflection\ReflectionFactoryAggregate;
use spaceonfire\Container\Factory\Reflection\ReflectionInvoker;
use spaceonfire\Container\FactoryContainer;

abstract class AbstractTestCase extends TestCase
{
    public function ormCapsuleProvider(): \Generator
    {
        yield [$this->makeOrmCapsule()];
    }

    protected function makeOrmCapsule(): OrmCapsule
    {
        $container = $this->makeContainer();
        $factory = new SpiralFactory($container);
        $config = new DatabaseConfig(require __DIR__ . '/Fixtures/config/dbal.php');
        $dbal = new DatabaseManager($config, $factory);

        $schemaFactory = new ArraySchemaRegistryFactory($dbal);
        $schemaFactory->addGenerator(
            new Generator\ResetTables(),
            new Generator\GenerateRelations(),
            new Generator\ValidateEntities(),
            new Generator\RenderTables(),
            new Generator\RenderRelations(),
            new Generator\SyncTables(),
            new Generator\GenerateTypecast(),
        );
        $schemaFactory->loadEntity(
            require __DIR__ . '/Fixtures/config/post.php',
            require __DIR__ . '/Fixtures/config/user.php',
            require __DIR__ . '/Fixtures/config/tag.php',
            require __DIR__ . '/Fixtures/config/todo_item.php',
        );

        $orm = new ORM(
            new OrmFactory($dbal, null, $factory),
            new Schema($schemaFactory->compile()),
        );
        $orm = $orm->withPromiseFactory(new EntityReferenceFactory());

        $container->define(ORMInterface::class, new Some($orm), true);
        $container->define(DatabaseProviderInterface::class, new Some($dbal), true);

        return new OrmCapsule($dbal, $orm);
    }

    private function makeContainer(): CompositeContainer
    {
        $factory = new ReflectionFactoryAggregate();
        $invoker = new ReflectionInvoker();

        $container = new CompositeContainer(
            new DefinitionContainer($factory, $invoker),
            new FactoryContainer($factory, $invoker),
        );

        $container->addServiceProvider(PluginsProvider::class);

        return $container;
    }
}
