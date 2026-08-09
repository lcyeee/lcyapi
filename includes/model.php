<?php
class Model
{
    public static function find($name)
    {
        return DB::fetch('SELECT * FROM models WHERE name = ?', [$name]);
    }

    public static function getById($id)
    {
        return DB::fetch('SELECT * FROM models WHERE id = ?', [(int)$id]);
    }

    public static function all($enabledOnly = false)
    {
        $sql = 'SELECT * FROM models';
        if ($enabledOnly) {
            $sql .= ' WHERE enabled = 1';
        }
        return DB::fetchAll($sql . ' ORDER BY sort ASC, id ASC');
    }

    public static function exists($name)
    {
        return self::find($name) !== false;
    }

    public static function create($data)
    {
        $fields = ['name', 'display_name', 'description', 'tags', 'input_price', 'output_price', 'context_length', 'max_output', 'type', 'enabled', 'sort'];
        $insert = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $insert[$field] = $data[$field];
            }
        }
        if (!isset($insert['type'])) {
            $insert['type'] = 'chat';
        }
        if (!isset($insert['enabled'])) {
            $insert['enabled'] = 1;
        }
        if (self::exists($insert['name'])) {
            return false;
        }
        try {
            return DB::insert('models', $insert);
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function update($id, $data)
    {
        $fields = ['name', 'display_name', 'description', 'tags', 'input_price', 'output_price', 'context_length', 'max_output', 'type', 'enabled', 'sort'];
        $update = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        if (empty($update)) {
            return true;
        }
        return DB::update('models', $update, 'id = ?', [(int)$id]) !== false;
    }

    public static function delete($id)
    {
        return DB::delete('models', 'id = ?', [(int)$id]);
    }

    public static function names()
    {
        $rows = DB::fetchAll('SELECT name FROM models WHERE enabled = 1 ORDER BY sort ASC, id ASC');
        return array_column($rows, 'name');
    }
}