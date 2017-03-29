<?php
/**
 * Created by PhpStorm.
 * User: Hisune <hi@hisune.com>
 * Date: 2017/3/23
 * Time: 11:30
 *
 * Auto generate PHPDoc for Echarts-PHP from option.json
 */

if (!php_sapi_name() == 'cli') {
    exit('Not in cli mode.');
}

class AutoGenerate
{

    public $optionJson = [
        'cn' => 'http://echarts.baidu.com/documents/cn/option.json',
        'en' => 'https://ecomfe.github.io/echarts-doc/public/en/documents/en/option.json',
    ];

    protected $language;

    protected $dir = __DIR__ . '/IDE';

    private $anyOf = [];

    public function __construct($language = 'en')
    {
        $this->setLanguage($language);
    }

    public function getLanguage()
    {
        return $this->language;
    }

    public function setLanguage($language)
    {
        if(!isset($this->optionJson[$language])){
            throw new \Exception('Not found language option setting: ' . $language);
        }else{
            $this->language = $language;
        }
        echo "init as {$this->language}\r\n";
    }

    public function generate()
    {
        $url = $this->optionJson[$this->language];
        echo "start generate\r\n";
        try{
            echo "get json from {$url}\r\n";
            $json = json_decode(file_get_contents($url), true);

            if($json && is_array($json) && isset($json['option']['properties'])){
                echo "start auto generate PHPDoc\r\n";
                $this->_properties($json['option']['properties']);
            }else{
                throw new \Exception('Unknown option format.');
            }
        }catch (\Exception $e){
            throw new \Exception($e->getMessage());
        }
    }

    protected function _properties($properties, $dir = '')
    {
        if(!$dir){
            echo "/**\r\n * Class ECharts\r\n * Created by Hisune EchartsPHP AutoGenerate.\r\n * @package Hisune\\EchartsPHP\r\n * \r\n";
        }

        foreach($properties as $top => $property){
            $classPropertyString = '';
            $top = ucfirst($top);

            if(isset($property['items']['anyOf']) && $property['items']['anyOf']){
                foreach ($property['items']['anyOf'] as $any){
                    if(isset($any['properties'])){
                        foreach ($any['properties'] as $classPropertyName => $classProperty){
                            $anyOfKey = $dir . '/' . $top . '/' . $classPropertyName;
                            if(!isset($this->anyOf[$anyOfKey])){
                                $classPropertyString .= $this->_propertyTemplate($classPropertyName, $classProperty, $top);
                                $this->anyOf[$anyOfKey] = true;
                            }
                        }
                        $this->_properties($any['properties'], $dir . '/' . $top);
                    }
                }
            }elseif(isset($property['properties'])){
                foreach ($property['properties'] as $classPropertyName => $classProperty){
                    $classPropertyString .= $this->_propertyTemplate($classPropertyName, $classProperty, $top);
                }
                $this->_properties($property['properties'], $dir . '/' . $top);
            }

            $dirToWrite = $this->dir . $dir;
            if($classPropertyString){
                if(!file_exists($dirToWrite)){
                    mkdir($dirToWrite, 0777, true);
                }
                file_put_contents($dirToWrite . '/' . $top . '.php', $this->_classTemplate($top, $dir, $classPropertyString));
            }

            // 输出ECharts类的property doc
            if(!$dir){
                $description = isset($property['descriptionCN']) ? $this->_replaceDescription($property['descriptionCN'], false) . "\r\n *" : '';
                if(file_exists($dirToWrite . '/' . $top . '.php')){
                    echo " * @property Doc\\IDE\\{$top} \$" . lcfirst($top) . "\r\n *   " . $description . "\r\n";
                }else{
                    echo " * @property callable \$" . lcfirst($top) . "\r\n *   " . $description . "\r\n";
                }
            }
        }

        if(!$dir){
            echo " */\r\n";
        }
    }

    protected function _classTemplate($name, $dir = '', $property = '')
    {
        $namespace = str_replace('/', '\\', $dir);

        return <<<PHP
<?php
/**
 * Created by Hisune EchartsPHP AutoGenerate.
 * @author: Hisune <hi@hisune.com>
 */

namespace Hisune\EchartsPHP\Doc\IDE{$namespace};

class {$name}
{    {$property}

}
PHP;
    }

    protected function _propertyTemplate($name, $detail, $dir)
    {
        $default = '';

        if(isset($detail['default'])){
            if(($detail['type'] == 'boolean' || in_array('boolean', $detail['type'])) && ($detail['default'] === true || $detail['default'] === false)){
                $default = " = " . ($detail['default'] ? 'true' : 'false');
            }elseif(($detail['type'] == 'number' || in_array('number', $detail['type'])) && is_numeric($detail['default'])){
                $default = " = " . $detail['default'];
            }else{
                $default = " = '" . str_replace("'", "\\'", trim($detail['default'], "'")) . "'";
            }
        }

        $detail['descriptionCN'] = isset($detail['descriptionCN']) ? $this->_replaceDescription($detail['descriptionCN']) : '';

        if(isset($detail['type'])){
            if(!isset($detail['properties'])){
                if(is_array($detail['type'])){
                    foreach ($detail['type'] as $k => $v){
                        $detail['type'][$k] = $this->_replacePropertyType($v);
                    }
                }else if(is_string($detail['type'])){
                    $detail['type'] = $this->_replacePropertyType($detail['type']);
                }
                $type = "/**\r\n     * @var " . (is_array($detail['type']) ? implode('|', $detail['type']) : $detail['type']) . ' ' . $detail['descriptionCN'] . "\r\n     */";
            }else{
                $type = "/**\r\n     * @var " . ltrim(str_replace('/', '\\', $dir) . '\\' . ucfirst($name), '\\') . ' ' . $detail['descriptionCN'] . "\r\n     */";
            }
        }else{
            $type = "/**\r\n     * @var " . $detail['descriptionCN'] . "\r\n     */";
        }

        return <<<PHP
        
        
    $type
    public \${$name}{$default};
PHP;

    }

    protected function _replacePropertyType($type)
    {
        switch ($type){
            case 'number':
                $type = 'int';
                break;
            case 'Object':
            case 'Array':
                $type = 'array';
                break;
            case 'Color':
                $type = 'string';
                break;
            case 'Function':
                $type = 'callable';
                break;
        }

        return $type;
    }

    protected function _replaceDescription($description, $multiLine = true)
    {
        if($multiLine){
            return str_replace("\n", "\r\n     * ", rtrim(str_replace(["&quot;", '&#39;', '/*', '*/'], '', strip_tags($description))));
        }else{
            $explode = explode("\n", $description);
            return rtrim(str_replace(["&quot;", '&#39;', '/*', '*/'], '', strip_tags($explode[0])));
        }
    }

}

$autoGenerate = new AutoGenerate(isset($argv[1]) ? $argv[1] : 'en');
$autoGenerate->generate();