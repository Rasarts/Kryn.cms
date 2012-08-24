<?php

namespace Core;

class Utils {

    private $inErrorHandler = false;

    public static function exceptionHandler($pException){
        $exceptionArray = array(
            'type' => $pException->getCode(),
            'message' => $pException->getMessage(),
            'file' => $pException->getFile(),
            'line' => $pException->getLine(),
        );
        self::errorHandler(get_class($pException).' ['.$pException->getCode().']',
            $pException->getMessage(), $pException->getFile(), $pException->getLine(),
            array_merge(array($exceptionArray), $pException->getTrace()));
    }

    public static function shutdownHandler(){
        chdir(PATH);
        $error = error_get_last();
        if($error['type'] == 1){
            self::errorHandler($error['type'], $error['message'], $error['file'], $error['line'], array(
                $error
            ));
        }
    }
    public static function errorHandler($pErrorCode, $pErrorStr, $pFile, $pLine, $pBacktrace = null){

        if ($inErrorHandler === true){
            print $pErrorCode.', '.$pErrorStr.' in '.$pFile.' at '.$pLine;
            if ($pBacktrace)
                print_r($pBacktrace);
            else 
                print_r(debug_backtrace());
            exit;
        }

        $inErrorHandler = true;

        if (is_numeric($pErrorCode)){
            $errorCodes = array(
                E_ERROR => 'E_ERROR',
                E_WARNING => 'E_WARNING',
                E_PARSE => 'E_PARSE',
                E_NOTICE => 'E_NOTICE',
                E_CORE_ERROR => 'E_CORE_ERROR',
                E_CORE_WARNING => 'E_CORE_WARNING',
                E_STRICT => 'E_STRICT',
                E_COMPILE_ERROR => 'E_COMPILE_ERROR',
                E_COMPILE_WARNING => 'E_COMPILE_WARNING',
                E_USER_ERROR => 'E_USER_ERROR',
                E_USER_WARNING => 'E_USER_WARNING',
                E_USER_NOTICE => 'E_USER_NOTICE',
            );
            if ($errorCodes[$pErrorCode]){
                $pErrorCode = $errorCodes[$pErrorCode];
            }
        }

        $msg = '<div style="margin-bottom: 15px; background-color: white; padding: 5px;">'.$pErrorStr.'</div>';

        $backtrace = $pBacktrace;
        if (!$backtrace){
            $backtrace = debug_backtrace();
        }

        tAssign('loadCodemirror', true);

        $traces = array();
        $count = count($backtrace);
        foreach ($backtrace as $trace){

            $trace['file'] = substr($trace['file'], strlen(PATH));
            $trace['id'] = $count--;
            // if ($trace['file'] == 'core/bootstrap.php' && $trace['line'] == 74) continue;
            if ($trace['file'] == 'core/global/internal.global.php' && $trace['line'] == 40) continue;

            $trace['code'] = self::getFileContent($trace['file'], $trace['line'], 5);
            $trace['relLine'] = $trace['line']-5;
            //$trace['args_string'] = implode(', ', $trace['args']);
            $traces[] = $trace;
        }

        tAssign('backtrace', $traces);
        //backtrace
        //$msg .= '<div style="padding: 5px; white-space: pre;">'..'</div>';

        //$msg .= self::getHighlightedFile($pFile, $pLine);


        kryn::internalError($pErrorCode, $msg, false);

        $inErrorHandler = true;

        exit;

    }

    public static function getFileContent($pFile, $pLine, $pOffset = 10){

        $fh = fopen($pFile, 'r');

        if ($fh){
            $line = 1;
            $code = '';
            while (($buffer = fgets($fh, 4096)) !== false) {
                
                if ($line >= ($pLine-$pOffset) && $line <= ($pLine+$pOffset))
                    $code .= $buffer;

                if ($line == $pLine)
                    $highlightLine = $line;

                $line++;
            }
            return $code;
        }
        return '';
    }


    /**
     * Stores all locked keys, so that we can release all,
     * on process terminating.
     * @var array
     */
    public static $lockedKeys = array();

    /**
     * Releases all locked aquired by this process.
     *
     * Will be called during process shutdown. (register_shutdown_function)
     */
    public static function releaseLocks(){
        foreach (self::$lockedKeys as $key => $value) {
            self::appRelease($key);
        }
    }

    /**
     * Locks the process until the lock of $pId has been acquired for this process.
     * If no lock has been acquired for this id, it returns without waiting true.
     *
     * If this installation is in a cluster array, we store the lock
     * into the current database backend, so that other cloud buddies know
     * that this id has been locked.
     * If it's a standalone installation, we use flock
     * 
     * @param  string $pId
     * @return boolean
     */
    public static function appLock($pId){

        if (Kryn::$config['cluster']){
            try {
                dbInsert('system_app_lock', array('id' => $pId));
                self::$lockedKeys[$pId] = true;
                return true;
            } catch(\Exception $e){
                //failed, we try it again each 1/4 ms
                usleep(250);
                return self::appLock($pId);
            }
        } else {

            $file = 'cache/lock/'.urlencode($pId).'.lock';
            $fh = fopen($file, 'c');
            if (!$fh) throw new \Exception('Can not create file for lock: '.$file);

            $state = flock($fh, LOCK_EX);
            if ($state) self::$lockedKeys[$pId] = true;
            return $state;
        }
        
    }

    /**
     * Tries to lock given id. If the id is already locked,
     * the function returns without waiting for the mutex to be unlocked.
     *
     * @see appLock()
     * 
     * @param  string $pMutexId
     * @return bool
     */
    public static function appTryLock($pId){

        if (Kryn::$config['cluster']){
            try {
                dbInsert('system_app_lock', array('id' => $pId));
                self::$lockedKeys[$pId] = true;
                return true;
            } catch(\Exception $e){
                //failed, we try it again each 1/4 ms
                return false;
            }
        } else {
            $file = 'cache/lock/'.urlencode($pId).'.lock';
            $fh = fopen($file, 'c');
            if (!$fh) throw new \Exception('Can not create file for lock: '.$file);

            $state = flock($fh, LOCK_EX|LOCK_NB);
            if ($state) self::$lockedKeys[$pId] = true;
            return $state;
        }
    }

    /**
     * Releases a lock.
     * If you're not the owner of the lock with $pId, then you'll kill it anyway.
     * 
     * @param  string $pId
     */
    public static function appRelease($pId){

        unset(self::$lockedKeys[$pId]);

        if (Kryn::$config['cluster']){
            try {
                dbDelete('system_app_lock', array('id' => $pId));
            } catch(\Exception $e){
            }
        } else {
            $file = 'cache/lock/'.urlencode($pId).'.lock';
            if (file_exists($file)){
                unlink($file);
            }
        }
    }

}


//when we'll be loaded, then we register our 
register_shutdown_function('\Core\Utils::releaseLocks');