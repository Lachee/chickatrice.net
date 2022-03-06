<?php
namespace kiss\schema;

interface SchemaInterface {
    /** Gets the schema properties */
    public static function getSchemaProperties($options = []);
}