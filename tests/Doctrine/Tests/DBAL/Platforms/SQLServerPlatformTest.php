<?php

namespace Doctrine\Tests\DBAL\Platforms;

use Doctrine\DBAL\Platforms\SQLServer2008Platform;
use Doctrine\DBAL\Types\Type;

class SQLServerPlatformTest extends AbstractPlatformTestCase
{
    public function createPlatform()
    {
        return new SQLServer2008Platform;
    }

    public function getGenerateTableSql()
    {
        return 'CREATE TABLE test (id INT IDENTITY NOT NULL, test NVARCHAR(255), PRIMARY KEY (id))';
    }

    public function getGenerateTableWithMultiColumnUniqueIndexSql()
    {
        return array(
            'CREATE TABLE test (foo NVARCHAR(255), bar NVARCHAR(255))',
            'CREATE UNIQUE INDEX UNIQ_D87F7E0C8C73652176FF8CAA ON test (foo, bar) WHERE foo IS NOT NULL AND bar IS NOT NULL'
        );
    }

    public function getGenerateAlterTableSql()
    {
        return array(
            'ALTER TABLE mytable ADD quota INT',
            'ALTER TABLE mytable DROP COLUMN foo',
            'ALTER TABLE mytable ALTER COLUMN baz NVARCHAR(255) NOT NULL',
            "ALTER TABLE mytable ADD CONSTRAINT DF_6B2BD609_78240498 DEFAULT 'def' FOR baz",
            'ALTER TABLE mytable ALTER COLUMN bloo BIT NOT NULL',
            "sp_RENAME 'mytable', 'userlist'",
            "DECLARE @sql NVARCHAR(MAX) = N''; " .
            "SELECT @sql += N'EXEC sp_rename N''' + dc.name + ''', N''' " .
            "+ REPLACE(dc.name, '6B2BD609', 'E2B58069') + ''', ''OBJECT'';' " .
            "FROM sys.default_constraints dc " .
            "JOIN sys.tables tbl ON dc.parent_object_id = tbl.object_id " .
            "WHERE tbl.name = 'userlist';" .
            "EXEC sp_executesql @sql"
        );
    }

    /**
     * @expectedException Doctrine\DBAL\DBALException
     */
    public function testDoesNotSupportRegexp()
    {
        $this->_platform->getRegexpExpression();
    }

    public function testGeneratesSqlSnippets()
    {
        $this->assertEquals('"', $this->_platform->getIdentifierQuoteCharacter(), 'Identifier quote character is not correct');
        $this->assertEquals('(column1 + column2 + column3)', $this->_platform->getConcatExpression('column1', 'column2', 'column3'), 'Concatenation expression is not correct');
    }

    public function testGeneratesTransactionsCommands()
    {
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_UNCOMMITTED)
        );
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_READ_COMMITTED)
        );
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_REPEATABLE_READ)
        );
        $this->assertEquals(
                'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',
                $this->_platform->getSetTransactionIsolationSQL(\Doctrine\DBAL\Connection::TRANSACTION_SERIALIZABLE)
        );
    }

    public function testGeneratesDDLSnippets()
    {
        $dropDatabaseExpectation = 'DROP DATABASE foobar';

        $this->assertEquals('SELECT * FROM SYS.DATABASES', $this->_platform->getListDatabasesSQL());
        $this->assertEquals('CREATE DATABASE foobar', $this->_platform->getCreateDatabaseSQL('foobar'));
        $this->assertEquals($dropDatabaseExpectation, $this->_platform->getDropDatabaseSQL('foobar'));
        $this->assertEquals('DROP TABLE foobar', $this->_platform->getDropTableSQL('foobar'));
    }

    public function testGeneratesTypeDeclarationForIntegers()
    {
        $this->assertEquals(
                'INT',
                $this->_platform->getIntegerTypeDeclarationSQL(array())
        );
        $this->assertEquals(
                'INT IDENTITY',
                $this->_platform->getIntegerTypeDeclarationSQL(array('autoincrement' => true)
        ));
        $this->assertEquals(
                'INT IDENTITY',
                $this->_platform->getIntegerTypeDeclarationSQL(
                        array('autoincrement' => true, 'primary' => true)
        ));
    }

    public function testGeneratesTypeDeclarationsForStrings()
    {
        $this->assertEquals(
                'NCHAR(10)',
                $this->_platform->getVarcharTypeDeclarationSQL(
                        array('length' => 10, 'fixed' => true)
        ));
        $this->assertEquals(
                'NVARCHAR(50)',
                $this->_platform->getVarcharTypeDeclarationSQL(array('length' => 50)),
                'Variable string declaration is not correct'
        );
        $this->assertEquals(
                'NVARCHAR(255)',
                $this->_platform->getVarcharTypeDeclarationSQL(array()),
                'Long string declaration is not correct'
        );
    }

    public function testPrefersIdentityColumns()
    {
        $this->assertTrue($this->_platform->prefersIdentityColumns());
    }

    public function testSupportsIdentityColumns()
    {
        $this->assertTrue($this->_platform->supportsIdentityColumns());
    }

    public function testSupportsSchemas()
    {
        $this->assertTrue($this->_platform->supportsSchemas());
    }

    public function testDoesNotSupportSavePoints()
    {
        $this->assertTrue($this->_platform->supportsSavepoints());
    }

    public function getGenerateIndexSql()
    {
        return 'CREATE INDEX my_idx ON mytable (user_name, last_login)';
    }

    public function getGenerateUniqueIndexSql()
    {
        return 'CREATE UNIQUE INDEX index_name ON test (test, test2) WHERE test IS NOT NULL AND test2 IS NOT NULL';
    }

    public function getGenerateForeignKeySql()
    {
        return 'ALTER TABLE test ADD FOREIGN KEY (fk_name_id) REFERENCES other_table (id)';
    }

    public function testModifyLimitQuery()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10, 0);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithEmptyOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithOffset()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username DESC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);
    }

    public function testModifyLimitQueryWithAscOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username ASC', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username ASC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithDescOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username DESC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithMultipleOrderBy()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM user ORDER BY username DESC, usereamil ASC', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY username DESC, usereamil ASC) AS doctrine_rownum FROM user) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithSubSelect()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname) dctrn_result', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM (SELECT u.id as uid, u.name as uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndOrder()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname ORDER BY u.name DESC) dctrn_result', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY u.name DESC) AS doctrine_rownum FROM (SELECT u.id as uid, u.name as uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id, u.name ORDER BY u.name DESC) dctrn_result', 10);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY name DESC) AS doctrine_rownum FROM (SELECT u.id, u.name) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    public function testModifyLimitQueryWithSubSelectAndMultipleOrder()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id as uid, u.name as uname ORDER BY u.name DESC, id ASC) dctrn_result', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY u.name DESC, id ASC) AS doctrine_rownum FROM (SELECT u.id as uid, u.name as uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id uid, u.name uname ORDER BY u.name DESC, id ASC) dctrn_result', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY u.name DESC, id ASC) AS doctrine_rownum FROM (SELECT u.id uid, u.name uname) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);

        $sql = $this->_platform->modifyLimitQuery('SELECT * FROM (SELECT u.id, u.name ORDER BY u.name DESC, id ASC) dctrn_result', 10, 5);
        $this->assertEquals('SELECT * FROM (SELECT *, ROW_NUMBER() OVER (ORDER BY name DESC, id ASC) AS doctrine_rownum FROM (SELECT u.id, u.name) dctrn_result) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15', $sql);
    }

    public function testModifyLimitQueryWithFromColumnNames()
    {
        $sql = $this->_platform->modifyLimitQuery('SELECT a.fromFoo, fromBar FROM foo', 10);
        $this->assertEquals('SELECT * FROM (SELECT a.fromFoo, fromBar, ROW_NUMBER() OVER (ORDER BY (SELECT 0)) AS doctrine_rownum FROM foo) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 1 AND 10', $sql);
    }

    /**
     * @group DDC-2470
     */
    public function testModifyLimitQueryWithOrderByClause()
    {
        $sql      = 'SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2 FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ? ORDER BY m0_.FECHAINICIO DESC';
        $expected = 'SELECT * FROM (SELECT m0_.NOMBRE AS NOMBRE0, m0_.FECHAINICIO AS FECHAINICIO1, m0_.FECHAFIN AS FECHAFIN2, ROW_NUMBER() OVER (ORDER BY m0_.FECHAINICIO DESC) AS doctrine_rownum FROM MEDICION m0_ WITH (NOLOCK) INNER JOIN ESTUDIO e1_ ON m0_.ESTUDIO_ID = e1_.ID INNER JOIN CLIENTE c2_ ON e1_.CLIENTE_ID = c2_.ID INNER JOIN USUARIO u3_ ON c2_.ID = u3_.CLIENTE_ID WHERE u3_.ID = ?) AS doctrine_tbl WHERE doctrine_rownum BETWEEN 6 AND 15';
        $actual   = $this->_platform->modifyLimitQuery($sql, 10, 5);

        $this->assertEquals($expected, $actual);
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteIdentifier()
    {
        $this->assertEquals('[fo][o]', $this->_platform->quoteIdentifier('fo]o'));
        $this->assertEquals('[test]', $this->_platform->quoteIdentifier('test'));
        $this->assertEquals('[test].[test]', $this->_platform->quoteIdentifier('test.test'));
    }

    /**
     * @group DDC-1360
     */
    public function testQuoteSingleIdentifier()
    {
        $this->assertEquals('[fo][o]', $this->_platform->quoteSingleIdentifier('fo]o'));
        $this->assertEquals('[test]', $this->_platform->quoteSingleIdentifier('test'));
        $this->assertEquals('[test.test]', $this->_platform->quoteSingleIdentifier('test.test'));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateClusteredIndex()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'));
        $idx->addFlag('clustered');
        $this->assertEquals('CREATE CLUSTERED INDEX idx ON tbl (id)', $this->_platform->getCreateIndexSQL($idx, 'tbl'));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateNonClusteredPrimaryKeyInTable()
    {
        $table = new \Doctrine\DBAL\Schema\Table("tbl");
        $table->addColumn("id", "integer");
        $table->setPrimaryKey(Array("id"));
        $table->getIndex('primary')->addFlag('nonclustered');

        $this->assertEquals(array('CREATE TABLE tbl (id INT NOT NULL, PRIMARY KEY NONCLUSTERED (id))'), $this->_platform->getCreateTableSQL($table));
    }

    /**
     * @group DBAL-220
     */
    public function testCreateNonClusteredPrimaryKey()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'), false, true);
        $idx->addFlag('nonclustered');
        $this->assertEquals('ALTER TABLE tbl ADD PRIMARY KEY NONCLUSTERED (id)', $this->_platform->getCreatePrimaryKeySQL($idx, 'tbl'));
    }

    public function testAlterAddPrimaryKey()
    {
        $idx = new \Doctrine\DBAL\Schema\Index('idx', array('id'), false, true);
        $this->assertEquals('ALTER TABLE tbl ADD PRIMARY KEY (id)', $this->_platform->getCreateIndexSQL($idx, 'tbl'));
    }

    protected function getQuotedColumnInPrimaryKeySQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, PRIMARY KEY ([create]))',
        );
    }

    protected function getQuotedColumnInIndexSQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL)',
            'CREATE INDEX IDX_22660D028FD6E0FB ON [quoted] ([create])',
        );
    }

    protected function getQuotedColumnInForeignKeySQL()
    {
        return array(
            'CREATE TABLE [quoted] ([create] NVARCHAR(255) NOT NULL, foo NVARCHAR(255) NOT NULL, [bar] NVARCHAR(255) NOT NULL)',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_RESERVED_KEYWORD FOREIGN KEY ([create], foo, [bar]) REFERENCES [foreign] ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_NON_RESERVED_KEYWORD FOREIGN KEY ([create], foo, [bar]) REFERENCES foo ([create], bar, [foo-bar])',
            'ALTER TABLE [quoted] ADD CONSTRAINT FK_WITH_INTENDED_QUOTATION FOREIGN KEY ([create], foo, [bar]) REFERENCES [foo-bar] ([create], bar, [foo-bar])',
        );
    }

    public function testGetCreateSchemaSQL()
    {
        $schemaName = 'schema';
        $sql = $this->_platform->getCreateSchemaSQL($schemaName);
        $this->assertEquals('CREATE SCHEMA ' . $schemaName, $sql);
    }

    public function testSchemaNeedsCreation()
    {
        $schemaNames = array(
            'dbo' => false,
            'schema' => true,
        );
        foreach ($schemaNames as $name => $expected) {
            $actual = $this->_platform->schemaNeedsCreation($name);
            $this->assertEquals($expected, $actual);
        }
    }
     /**
     * @group DBAL-701
     */
    public function testListTableColumnsDefaultSchema()
    {
        $expected = "SELECT    col.name,
                          type.name AS type,
                          col.max_length AS length,
                          ~col.is_nullable AS notnull,
                          def.definition AS [default],
                          col.scale,
                          col.precision,
                          col.is_identity AS autoincrement,
                          col.collation_name AS collation
                FROM      sys.columns AS col
                JOIN      sys.types AS type
                ON        col.user_type_id = type.user_type_id
                JOIN      sys.objects AS obj
                ON        col.object_id = obj.object_id
                JOIN      sys.schemas
                ON        obj.schema_id = schemas.schema_id
                LEFT JOIN sys.default_constraints def
                ON        col.default_object_id = def.object_id
                AND       col.object_id = def.parent_object_id
                WHERE     obj.type = 'U'
                AND       (obj.name = 'tableName' AND schemas.name = SCHEMA_NAME())";

        $this->assertEquals($expected, $this->_platform->getListTableColumnsSQL('tableName'));
    }

     /**
     * @group DBAL-701
     */
    public function testListTableColumnsNamedSchema()
    {
        $expected = "SELECT    col.name,
                          type.name AS type,
                          col.max_length AS length,
                          ~col.is_nullable AS notnull,
                          def.definition AS [default],
                          col.scale,
                          col.precision,
                          col.is_identity AS autoincrement,
                          col.collation_name AS collation
                FROM      sys.columns AS col
                JOIN      sys.types AS type
                ON        col.user_type_id = type.user_type_id
                JOIN      sys.objects AS obj
                ON        col.object_id = obj.object_id
                JOIN      sys.schemas
                ON        obj.schema_id = schemas.schema_id
                LEFT JOIN sys.default_constraints def
                ON        col.default_object_id = def.object_id
                AND       col.object_id = def.parent_object_id
                WHERE     obj.type = 'U'
                AND       (obj.name = 'tableName' AND schemas.name = 'schema')";

        $this->assertEquals($expected, $this->_platform->getListTableColumnsSQL('schema.tableName'));
    }

    /**
    * @group DBAL-701
    */
    public function testListTableForeignKeysDefaultSchema()
    {
        $expected = "SELECT f.name AS ForeignKey,
                SCHEMA_NAME (f.SCHEMA_ID) AS SchemaName,
                OBJECT_NAME (f.parent_object_id) AS TableName,
                COL_NAME (fc.parent_object_id,fc.parent_column_id) AS ColumnName,
                SCHEMA_NAME (o.SCHEMA_ID) ReferenceSchemaName,
                OBJECT_NAME (f.referenced_object_id) AS ReferenceTableName,
                COL_NAME(fc.referenced_object_id,fc.referenced_column_id) AS ReferenceColumnName,
                f.delete_referential_action_desc,
                f.update_referential_action_desc
                FROM sys.foreign_keys AS f
                INNER JOIN sys.foreign_key_columns AS fc
                INNER JOIN sys.objects AS o ON o.OBJECT_ID = fc.referenced_object_id
                ON f.OBJECT_ID = fc.constraint_object_id
                WHERE (OBJECT_NAME (f.parent_object_id) = 'tableName' AND SCHEMA_NAME (f.schema_id) = SCHEMA_NAME())";

        $this->assertEquals($expected, $this->_platform->getListTableForeignKeysSQL('tableName'));
    }

    /**
    * @group DBAL-701
    */
    public function testListTableForeignKeysNamedSchema()
    {
        $expected = "SELECT f.name AS ForeignKey,
                SCHEMA_NAME (f.SCHEMA_ID) AS SchemaName,
                OBJECT_NAME (f.parent_object_id) AS TableName,
                COL_NAME (fc.parent_object_id,fc.parent_column_id) AS ColumnName,
                SCHEMA_NAME (o.SCHEMA_ID) ReferenceSchemaName,
                OBJECT_NAME (f.referenced_object_id) AS ReferenceTableName,
                COL_NAME(fc.referenced_object_id,fc.referenced_column_id) AS ReferenceColumnName,
                f.delete_referential_action_desc,
                f.update_referential_action_desc
                FROM sys.foreign_keys AS f
                INNER JOIN sys.foreign_key_columns AS fc
                INNER JOIN sys.objects AS o ON o.OBJECT_ID = fc.referenced_object_id
                ON f.OBJECT_ID = fc.constraint_object_id
                WHERE (OBJECT_NAME (f.parent_object_id) = 'tableName' AND SCHEMA_NAME (f.schema_id) = 'schema')";

        $this->assertEquals($expected, $this->_platform->getListTableForeignKeysSQL('schema.tableName'));
    }

    /**
    * @group DBAL-701
    */
    public function testListTableIndexesDefaultSchema()
    {
        $expected = "SELECT idx.name AS key_name,
                       col.name AS column_name,
                      ~idx.is_unique AS non_unique,
                       idx.is_primary_key AS [primary],
                       CASE idx.type
                           WHEN '1' THEN 'clustered'
                           WHEN '2' THEN 'nonclustered'
                           ELSE NULL
                       END AS flags
                FROM sys.tables AS tbl
                JOIN sys.indexes AS idx ON tbl.object_id = idx.object_id
                JOIN sys.index_columns AS idxcol ON idx.object_id = idxcol.object_id AND idx.index_id = idxcol.index_id
                JOIN sys.columns AS col ON idxcol.object_id = col.object_id AND idxcol.column_id = col.column_id
                WHERE (tbl.name = 'tableName' AND SCHEMA_NAME (tbl.schema_id) = SCHEMA_NAME())
                ORDER BY idx.index_id ASC, idxcol.index_column_id ASC";

        $this->assertEquals($expected, $this->_platform->getListTableIndexesSQL('tableName'));
    }

    /**
    * @group DBAL-701
    */
    public function testListTableIndexesNamedSchema()
    {
        $expected = "SELECT idx.name AS key_name,
                       col.name AS column_name,
                      ~idx.is_unique AS non_unique,
                       idx.is_primary_key AS [primary],
                       CASE idx.type
                           WHEN '1' THEN 'clustered'
                           WHEN '2' THEN 'nonclustered'
                           ELSE NULL
                       END AS flags
                FROM sys.tables AS tbl
                JOIN sys.indexes AS idx ON tbl.object_id = idx.object_id
                JOIN sys.index_columns AS idxcol ON idx.object_id = idxcol.object_id AND idx.index_id = idxcol.index_id
                JOIN sys.columns AS col ON idxcol.object_id = col.object_id AND idxcol.column_id = col.column_id
                WHERE (tbl.name = 'tableName' AND SCHEMA_NAME (tbl.schema_id) = 'schema')
                ORDER BY idx.index_id ASC, idxcol.index_column_id ASC";

        $this->assertEquals($expected, $this->_platform->getListTableIndexesSQL('schema.tableName'));
    }


}
