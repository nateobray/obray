<?php
namespace obray;

class oCoreProjectEnum {
    const ODBO = 'ODBO';
    const OOBJECT = 'OObject';
}

if (!class_exists( 'obray\oObject' )) { die(); }

/**
 * Class used for logging/debugging
 */
class oLogger extends \Psr\Log\AbstractLogger {

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context Context may container a projectEnum and an exception key for additional handling
     *
     * @return void
     */
    
    public function log($level, $message, array $context=array())
    {
        if( !in_array($level ,[
            \Psr\Log\LogLevel::EMERGENCY,
            \Psr\Log\LogLevel::ALERT,
            \Psr\Log\LogLevel::CRITICAL,
            \Psr\Log\LogLevel::ERROR,
            \Psr\Log\LogLevel::WARNING,
            \Psr\Log\LogLevel::NOTICE,
            \Psr\Log\LogLevel::INFO,
            \Psr\Log\LogLevel::DEBUG
        ])) {
            throw new \Psr\Log\InvalidArgumentException("Must specify log level in \Psr\Log\LogLevel",500);
        }
        if (!empty($context['exception'])){
            $exception = $context['exception'];
            $message = $exception->getMessage().PHP_EOL;
            if($customMessage != "") {
                $message .= "Custom Message: ".$customMessage.PHP_EOL;
            }
            $message .= $this->getStackTrace($exception);
        }
        $filepath = $this->getFilePath($level,!empty($context["projectTypeEnum"])?$context["projectEnum"]:'');
        $message = date('Y-m-d h:i:s', time()).' '.$message.PHP_EOL;
        if( $filepath ){
            $this->writeLog($filepath, $message);
        }
        $this->console('%s',$message,$this->getColor($level));
    }

    /**
     * gets the path where we want to write our log files to
     *
     * @param mixed  $level
     * @param string $oProjectEnum
     *
     * @return void
     */

    private function getFilePath($level, $oProjectEnum='') 
    {
        if( defined('__LOGS__') && defined('__APP__') ){
            $filepath = __LOGS__.__APP__.'/'.(!empty($oProjectEnum)?($oProjectEnum.'/'):'').$level.'/'.Date('Y-m-d').'.log';
            return $filepath;
        }
        return false;
    }

    /**
     * Writes a log to file
     *
     * @param string  $filepath
     * @param string $message
     *
     * @return void
     */

    private function writeLog($filepath, $message) 
    {
        if(!file_exists(dirname($filepath))) {
            $old_umask = umask(0);
            mkdir(dirname($filepath),0777, true);
            umask($old_umask);
        }
        $createFile = !file_exists($filepath);
        file_put_contents($filepath, $message.PHP_EOL, FILE_APPEND | LOCK_EX);
        if($createFile) {
            chmod($filepath, 0666);
        }
    }

    /**
     * Writes a log to the console
     *
     * @param string  $filepath
     * @param string $message
     *
     * @return void
     */

    public function console(){

        $args = func_get_args();
        if( PHP_SAPI === 'cli' && !empty($args) ){

            if( is_array($args[0]) || is_object($args[0]) ) {
                print_r($args[0]);
            } else if( count($args) === 3 && $args[1] !== NULL && $args[2] !== NULL ){
                $colors = array(
                    // text color
                    "Black" =>              "\033[30m",
                    "Red" =>                "\033[31m",
                    "Green" =>              "\033[32m",
                    "Yellow" =>             "\033[33m",
                    "Blue" =>               "\033[34m",
                    "Purple" =>             "\033[35m",
                    "Cyan" =>               "\033[36m",
                    "White" =>              "\033[37m",
                    // text color bold
                    "BlackBold" =>          "\033[30m",
                    "RedBold" =>            "\033[1;31m",
                    "GreenBold" =>          "\033[1;32m",
                    "YellowBold" =>         "\033[1;33m",
                    "BlueBold" =>           "\033[1;34m",
                    "PurpleBold" =>         "\033[1;35m",
                    "CyanBold" =>           "\033[1;36m",
                    "WhiteBold" =>          "\033[1;37m",
                    // text color muted
                    "RedMuted" =>           "\033[2;31m",
                    "GreenMuted" =>         "\033[2;32m",
                    "YellowMuted" =>        "\033[2;33m",
                    "BlueMuted" =>          "\033[2;34m",
                    "PurpleMuted" =>        "\033[2;35m",
                    "CyanMuted" =>          "\033[2;36m",
                    "WhiteMuted" =>         "\033[2;37m",
                    // text color underlined
                    "BlackUnderline" =>     "\033[4;30m",
                    "RedUnderline" =>       "\033[4;31m",
                    "GreenUnderline" =>     "\033[4;32m",
                    "YellowUnderline" =>    "\033[4;33m",
                    "BlueUnderline" =>      "\033[4;34m",
                    "PurpleUnderline" =>    "\033[4;35m",
                    "CyanUnderline" =>      "\033[4;36m",
                    "WhiteUnderline" =>     "\033[4;37m",
                    // text color background
                    "RedBackground" =>      "\033[7;31m",
                    "GreenBackground" =>    "\033[7;32m",
                    "YellowBackground" =>   "\033[7;33m",
                    "BlueBackground" =>     "\033[7;34m",
                    "PurpleBackground" =>   "\033[7;35m",
                    "CyanBackground" =>     "\033[7;36m",
                    "WhiteBackground" =>    "\033[7;37m",
                    // reset - auto called after each of the above by default
                    "Reset"=>               "\033[0m"
                );
                $color = $colors[$args[2]];
                printf($color.array_shift($args)."\033[0m",array_shift($args) );
            } else {
                printf( array_shift($args),array_shift($args) );
            }
        }
    }

    public function getColor($level)
    {
        switch($level){
            case \Psr\Log\LogLevel::EMERGENCY:
                return "RedBold";
                break;
            case \Psr\Log\LogLevel::ALERT:
                return "RedBackground";
                break;
            case \Psr\Log\LogLevel::CRITICAL:
                return "Red";
                break;
            case \Psr\Log\LogLevel::ERROR:
                return "Red";
                break;
            case \Psr\Log\LogLevel::WARNING:
                return "Yellow";
                break;
            case \Psr\Log\LogLevel::NOTICE:
                return "Purple";
                break;
            case \Psr\Log\LogLevel::INFO:
                return "Blue";
                break;
            case \Psr\Log\LogLevel::DEBUG:
                return "White";
                break;
        }
        return "";
    }

}   