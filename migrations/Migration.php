<?php

/*
 * This file is part of the Dektrium project.
 *
 * (c) Dektrium project <http://github.com/dektrium/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dektrium\user\migrations;

use Yii;

/**
 * @author Dmitry Erofeev <dmeroff@gmail.com>
 */
class Migration extends \yii\db\Migration
{
    /**
     * @var string
     */
    protected $tableOptions;
    protected $restrict = 'RESTRICT';
    protected $cascade = 'CASCADE';
    protected $dbType;
    

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        switch ($this->db->driverName) {
            case 'mysql':
                $this->tableOptions = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
                $this->dbType = 'mysql';
                break;
            case 'pgsql':
                $this->tableOptions = null;
                $this->dbType = 'pgsql';
                break;
            case 'dblib':
            case 'mssql':
            case 'sqlsrv':
                $this->restrict = 'NO ACTION';
                $this->tableOptions = null;
                $this->dbType = 'sqlsrv';
                break;
            default:
                throw new \RuntimeException('Your database is not supported!');
        }
    }
    
    public function dropTable($table)
    {
        if ($this->dbType == 'sqlsrv') {
            $this->dropTableConstraints($table);
        }
        return parent::dropTable($table);
    }
    
    public function dropColumn($table, $column)
    {
        if ($this->dbType == 'sqlsrv') {
            $this->dropColumnConstraints($table, $column);
        }
        return parent::dropColumn($table, $column);
    }
    
    /*
     *  Drops contratints and Indexes referencind a Table Column
     */
    public function dropColumnConstraints($table, $column)
    {
        $table = Yii::$app->db->schema->getRawTableName($table);
        $cmd = Yii::$app->db->createCommand('SELECT name FROM sys.default_constraints
                                WHERE parent_object_id = object_id(:table)
                                AND type = \'D\' AND parent_column_id = (
                                    SELECT column_id 
                                    FROM sys.columns 
                                    WHERE object_id = object_id(:table)
                                          AND name = :column
                                )', [ ':table' => $table, ':column' => $column ]);
                                
        $constraints = $cmd->queryAll();
        foreach ($constraints as $c) {
            $this->execute('ALTER TABLE '.Yii::$app->db->quoteTableName($table).' DROP CONSTRAINT '.Yii::$app->db->quoteColumnName($c['name']));
        }
        
        // checking for indexes
        $cmd = Yii::$app->db->createCommand('SELECT ind.name FROM sys.indexes ind
                                                INNER JOIN sys.index_columns ic 
                                                    ON  ind.object_id = ic.object_id and ind.index_id = ic.index_id 
                                                INNER JOIN sys.columns col 
                                                    ON ic.object_id = col.object_id and ic.column_id = col.column_id 
                                                WHERE ind.object_id = object_id(:table)
                                                AND col.name = :column',
                                [ ':table' => $table, ':column' => $column ]);
                                
        $indexes = $cmd->queryAll();
        foreach ($indexes as $i) {
            $this->dropIndex($i['name'],$table);
        }
    }
    
    /*
     *  Drops contratints referencing the Table
     */
    public function dropTableConstraints($table)
    {
        $table = Yii::$app->db->schema->getRawTableName($table);
        $cmd = Yii::$app->db->createCommand('SELECT name, OBJECT_NAME(parent_object_id) as tbl FROM sys.foreign_keys
                                WHERE referenced_object_id = object_id(:table)',
                                [ ':table' => $table ]);
        $constraints = $cmd->queryAll();
        foreach ($constraints as $c) {
            echo 'Dropping constrain: '.$c['name']."\n";
            $this->execute('ALTER TABLE '.Yii::$app->db->quoteTableName($c['tbl']).' DROP CONSTRAINT '.Yii::$app->db->quoteColumnName($c['name']));
        }
    }
    
}
