<?php
class Validator
{
    private $data;
    private $rules;
    private $errors = [];
    private $messages = [];

    public function __construct($data, $messages = [])
    {
        $this->data = $data;
        $this->messages = $messages;
    }

    public static function make($data, $rules = [])
    {
        $instance = new self($data);
        foreach ($rules as $field => $rule) {
            $instance->rule($field, $rule);
        }
        return $instance;
    }

    public function rule($field, $rule)
    {
        if (is_array($rule)) {
            $rule = implode('|', $rule);
        }
        $this->rules[$field] = $rule;
        return $this;
    }

    public function passes()
    {
        foreach ($this->rules as $field => $rule) {
            $value = isset($this->data[$field]) ? $this->data[$field] : null;
            $rules = explode('|', $rule);
            foreach ($rules as $single) {
                $parts = explode(':', $single, 2);
                $name = $parts[0];
                $param = isset($parts[1]) ? $parts[1] : '';
                $ok = $this->validate($name, $field, $value, $param);
                if (!$ok) {
                    $this->errors[$field][] = $this->message($field, $name, $value, $param);
                    break;
                }
            }
        }
        return empty($this->errors);
    }

    public function fails()
    {
        return !$this->passes();
    }

    public function errors()
    {
        return $this->errors;
    }

    public function firstError()
    {
        foreach ($this->errors as $errors) {
            return $errors[0];
        }
        return '';
    }

    private function validate($name, $field, $value, $param)
    {
        switch ($name) {
            case 'required':
                return !$this->emptyValue($value);
            case 'email':
                return $this->emptyValue($value) || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return $this->emptyValue($value) || filter_var($value, FILTER_VALIDATE_URL) !== false;
            case 'ip':
                return $this->emptyValue($value) || filter_var($value, FILTER_VALIDATE_IP) !== false;
            case 'integer':
                return $this->emptyValue($value) || filter_var($value, FILTER_VALIDATE_INT) !== false;
            case 'numeric':
                return $this->emptyValue($value) || is_numeric($value);
            case 'min':
                return $this->emptyValue($value) || (is_numeric($value) ? $value >= (float)$param : mb_strlen($value) >= (int)$param);
            case 'max':
                return $this->emptyValue($value) || (is_numeric($value) ? $value <= (float)$param : mb_strlen($value) <= (int)$param);
            case 'length':
                return $this->emptyValue($value) || mb_strlen($value) === (int)$param;
            case 'alpha':
                return $this->emptyValue($value) || preg_match('/^[a-zA-Z]+$/', $value);
            case 'alpha_num':
                return $this->emptyValue($value) || preg_match('/^[a-zA-Z0-9]+$/', $value);
            case 'username':
                return $this->emptyValue($value) || preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $value);
            case 'in':
                return $this->emptyValue($value) || in_array($value, explode(',', $param), true);
            case 'regex':
                return $this->emptyValue($value) || preg_match($param, $value);
            case 'unique':
                $parts = explode(',', $param);
                $table = $parts[0];
                $column = isset($parts[1]) ? $parts[1] : $field;
                $excludeId = isset($parts[2]) ? (int)$parts[2] : 0;
                $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $column . ' = ?';
                $params = [$value];
                if ($excludeId > 0) {
                    $sql .= ' AND id != ?';
                    $params[] = $excludeId;
                }
                return (int)DB::value($sql, $params) === 0;
            case 'confirmed':
                return $value === (isset($this->data[$field . '_confirmation']) ? $this->data[$field . '_confirmation'] : null);
            default:
                return true;
        }
    }

    private function emptyValue($value)
    {
        return $value === null || $value === '' || (is_array($value) && count($value) === 0);
    }

    private function message($field, $rule, $value, $param)
    {
        $labels = [
            'required' => '必填',
            'email' => '邮箱格式不正确',
            'url' => 'URL 格式不正确',
            'ip' => 'IP 地址格式不正确',
            'integer' => '必须是整数',
            'numeric' => '必须是数字',
            'min' => '长度或数值不能小于 ' . $param,
            'max' => '长度或数值不能大于 ' . $param,
            'length' => '长度必须为 ' . $param,
            'alpha' => '只能包含字母',
            'alpha_num' => '只能包含字母和数字',
            'username' => '只能包含字母、数字、下划线和横线（3-50 位）',
            'in' => '不在允许的范围内',
            'regex' => '格式不正确',
            'unique' => '已存在',
            'confirmed' => '两次输入不一致',
        ];
        $message = isset($this->messages[$field]) ? $this->messages[$field] : (isset($labels[$rule]) ? $labels[$rule] : '不符合规则');
        return $field . ' 字段' . $message;
    }
}