<?php
class connect extends PDO
{
    public function __construct($dbname)
    {
        $file = 'config.ini';
        if (!$settings = parse_ini_file($file, TRUE)) throw new exception('Unable to open ' . $file . '.');

        $dns = $settings['mysql']['driver'] . ':host=' . $settings['mysql']['host'] . ((!empty($settings['mysql']['port'])) ? (';port=' . $settings['mysql']['port']) : '') . ';dbname=' . $dbname;
        try
        {
            parent::__construct($dns, $settings['mysql']['username'], $settings['mysql']['password']);
        }
        catch( Exception $Exception )
        {
            print_r($Exception->getMessage());
        }
    }
}


?>