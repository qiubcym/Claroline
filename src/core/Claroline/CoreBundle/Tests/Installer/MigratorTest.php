<?php

namespace Claroline\CoreBundle\Installer;

use Claroline\CoreBundle\Testing\TransactionalTestCase;
use Claroline\CoreBundle\Installer\Loader;

class MigratorTest extends TransactionalTestCase
{
    /** @var Claroline\CoreBundle\Installer\Migrator */
    private $migrator;
    
    /** @var Claroline\CoreBundle\Installer\Loader */
    private $loader;
    
    public function setUp()
    {
        parent::setUp();
        
        $container = $this->client->getContainer();
        $this->migrator = $container->get('claroline.plugin.migrator');
        $this->loader = $container->get('claroline.plugin.loader');
        $this->overrideDefaultPluginDirectories($this->loader);
    }
    
    protected function tearDown() 
    {
        $table = $this->getTableFromSchema('valid_withmigrations_stuffs');
        
        if ($table)
        {
            $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
            $plugin = $this->loader->load($pluginFQCN);
            $this->migrator->remove($plugin);
        }
        
        parent::tearDown();
    }
    
    public function testVersionsTableIsCreatedAndPopulatedOnInstall()
    {
        $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
        $plugin = $this->loader->load($pluginFQCN);

        $this->migrator->install($plugin);
        
        $query = "SELECT * FROM valid_withmigrations_doctrine_migration_versions";
        $result = $this->getConnection()->fetchAll($query);
        
        $this->assertEquals(2, count($result));
    }
    
    public function testMigrationsAreEffectivelyRunOnInstall()
    {
        $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
        $plugin = $this->loader->load($pluginFQCN);

        $this->migrator->install($plugin);
        
        $schema = $this->getSchemaManager()->createSchema();
        $table = $schema->getTable('valid_withmigrations_stuffs');
        
        $this->assertTrue($table->hasColumn('id'));
        $this->assertTrue($table->hasColumn('name'));
        $this->assertTrue($table->hasColumn('last_modified'));      
    }
    
    public function testVersionsTableIsCreatedAndEmptiedOnRemove()
    {
        $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
        $plugin = $this->loader->load($pluginFQCN);

        $this->migrator->install($plugin);
        $this->migrator->remove($plugin);
        
        $query = "SELECT * FROM valid_withmigrations_doctrine_migration_versions";
        $result = $this->getConnection()->fetchAll($query);
        
        $this->assertEquals(0, count($result));
    }
    
    public function testMigrationsAreEffectivelyRunOnRemove()
    {
        $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
        $plugin = $this->loader->load($pluginFQCN);

        $this->migrator->install($plugin);
        $this->migrator->remove($plugin);
        
        $schema = $this->getSchemaManager()->createSchema();
        $this->assertFalse($schema->hasTable('valid_withmigrations_stuffs'));               
    }
    
    public function testVersionsTableIsPopulatedOnUpgrade()
    {
        $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
        $plugin = $this->loader->load($pluginFQCN);

        $this->migrator->migrate($plugin, '00000000000001');
                
        $query = "SELECT * FROM valid_withmigrations_doctrine_migration_versions";
        $result = $this->getConnection()->fetchAll($query);        
        $this->assertEquals(1, count($result));
        
        $this->migrator->install($plugin);
        
        $result = $this->getConnection()->fetchAll($query);        
        $this->assertEquals(2, count($result));       
    }
    
    public function testMigrationsAreEffectivelyRunInRightOrderOnUpgrade()
    {
        $pluginFQCN = 'Valid\WithMigrations\ValidWithMigrations';
        $plugin = $this->loader->load($pluginFQCN);

        $this->migrator->migrate($plugin, '00000000000001');
        $schema = $this->getSchemaManager()->createSchema();
        $table = $schema->getTable('valid_withmigrations_stuffs');
        $this->assertTrue($table->hasColumn('id'));
        $this->assertTrue($table->hasColumn('name'));
        $this->assertFalse($table->hasColumn('last_modified'));
        
        $this->migrator->install($plugin);
        
        $schema = $this->getSchemaManager()->createSchema();
        $table = $schema->getTable('valid_withmigrations_stuffs');
        $this->assertTrue($table->hasColumn('last_modified'));       
    }   
    
    private function getConnection()
    {
        return $this->client->getConnection();
    }
    
    private function getSchemaManager()
    {
        return $this->getConnection()->getSchemaManager();
    }
    
    private function getTableFromSchema($tableName)
    {
        $schema = $this->client
            ->getConnection()
            ->getSchemaManager()
            ->createSchema();
        
        if ($schema->hasTable($tableName))
        {
            return $schema->getTable($tableName);
        }
        
        return null;
    }
    
    private function overrideDefaultPluginDirectories(Loader $loader)
    {
        $ds = DIRECTORY_SEPARATOR;
        $stubDir = __DIR__ . "{$ds}..{$ds}Stub{$ds}plugin{$ds}";
        $loader->setPluginDirectories(
            array(
                'extension' => "{$stubDir}extension",
                'tool' => "{$stubDir}tool"
            )
        );
    }
}