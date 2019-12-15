<?php
namespace Flm;
use \Exception;

class TaskController {
    
    public $info;
    public function __construct($task_file = null) {
        
        if(!is_null($task_file)){
            $this->info = json_decode(file_get_contents($task_file));

            $this->log = $this->info->temp->dir. 'log';
            umask(0);
         }
    }
    
    public function run(){


        
        if(isset($this->info->params->workdir)
            && !empty($this->info->params->workdir)) {
            
            chdir ($this->info->params->workdir);
        }
        
      //  var_dump('Task info: -------', $this->info);
        
     //   $this->writeLog("\n0: Started ");
        
        if(method_exists($this, $this->info->action)) {
           $success= call_user_func(array($this,  $this->info->action));

            $this->writeLog("\n--- Done");
        }


        $this->recursiveRemove(array($this->info->temp->dir), false);
      // rmdir($this->info->temp->dir);
    }
    
    public function compressFiles()
    {

        chdir ($this->info->params->options->workdir);
        $hasFail = false;
            try {
           $cmd = FsUtils::getArchiveCompressCmd($this->info->params);
                $output =  $this->LogCmdExec($cmd);

            //    var_dump(__METHOD__, $cmd, $output);

            }
            catch (Exception $err) {
                var_dump($err->getMessage(), $err->getTraceAsString());
            }

        return $hasFail === false;

    }

    public function recursiveCopy() {

        $total = count($this->info->params->files);
        $hasFail = false;
      foreach ($this->info->params->files as $i => $file) {


          $copycmd = FsUtils::getCopyCmd($file, $this->info->params->to);

          try {
                $this->LogCmdExec($copycmd);
                $this->writeLog('OK: ('.++$i.'/'.$total.') -> '. $file);
          } catch (Exception $err) {

              $this->writeLog('Failed: '.$file . ' -> ' . $file);
              $hasFail = true;
          }

      }

        return $hasFail === false;
    }
    
    public function recursiveMove() {
        foreach ($this->info->params->files as $file) {
          
          
          $renamecmd = 'mv -f '.Helper::mb_escapeshellarg($file) . ' ' . Helper::mb_escapeshellarg($this->info->params->to);
          
          try {
              
                $this->LogCmdExec($renamecmd);
                $this->writeLog('OK: '.$file. ' ');
          } catch (Exception $err) {
              
              $this->writeLog('Failed: '.$file);
          }
      }
        
    }
    
   public function recursiveRemove($files = null, $verbose = true) {
        
      $files = is_null($files) ? $this->info->params->files : $files;
      
      foreach ($files as $file) {
          
          
          $rmcmd = FsUtils::getRemoveCmd($file);
     
          try {
                $this->LogCmdExec($rmcmd);
               if($verbose) {$this->writeLog('0: OK: '.$file. ' ');}
          } catch (Exception $err) {
              
            if($verbose) {  $this->writeLog('0: Failed: '.$file); }
          }
      }
            
    }
    
    
    public function sfvCreate ()
    {
  
        if (($sfvfile  = fopen($this->info->params->target, "abt")) === FALSE) {
            
             $this->writeLog('0: SFV HASHING FAILED. File not writable '.$this->info->params->target);
        }

        // comments        
        fwrite($sfvfile, "; ruTorrent filemanager;\n");


        $check_files = new SFV($this->info->params->files);
        $fcount = count($this->info->params->files);

        
        foreach ($check_files as $i => $sfvinstance) {
            
            $file = $sfvinstance->getCurFile();
            $msg = '('.$i.'/'.$fcount. ') Hashing '.$file.' ... ';

           try {
              $hash = SFV::getFileHash($file);

              fwrite($sfvfile, end(explode('/', $file)).' '.$hash."\n");
              $this->writeLog($msg.' - OK '.$hash); 
          } catch (Exception $err) {
              $this->writeLog($msg. ' - FAILED:'.$err->getMessage());

          }


      }
          
      fclose($sfvfile);

        
        
        
    }
    
    public function sfvCheck ()
    {


        $check_files = new SFV($this->info->params->target);
        
        $fcount = $check_files->length();
        
        
        foreach ($check_files as $i => $item) {
            
            $file = $item->getCurFile();

            $msg = '('.$i.'/'.$fcount. ') Checking '.trim($file).' ... ';

           try {
                   
             if(!$item->checkFileHash() ) {
              $this->writeLog($msg. '- FAILED: hash mismatch ');
             }
              $this->writeLog($msg.'- OK '); 
          } catch (Exception $err) {
          
              $this->writeLog($msg. '- FAILED:'.$err->getMessage());

          }

          }
        

        $this->writeLog("OK: files match\n");
    }

    public function extract ()
    {

            $cmd = FsUtils::getArchiveExtractCmd($this->info->params);

            try {
      
              if(!is_dir($this->info->params->to)) {
                    mkdir($this->info->params->to);
                }
                $output =  $this->LogCmdExec($cmd);
            }
            catch (Exception $err) {
                var_dump($err);
            }
        }
    
    public function LogCmdExec($cmd) {
     //    $cmd =  $cmd.' > '.$this->log.' 2>&1';
        $cmd =  $cmd;
        $output = [];
        $res = exec($cmd, $output, $exitCode);

    //    passthru($cmd, $exitCode);

        if($exitCode > 0) {
            $logdata = [];
           // $logdata =  $this->readLog();

            if(isset($logdata['lines']))
            {
                $output = array_merge($output, is_array($logdata['lines']) ? $logdata['lines'] : [$logdata['lines']]);
            }
            throw new Exception('Command error: '. implode("\n",$output), $exitCode);
            
        }

        return $output;
    }

    public function readLog($lpos = 0) {

        return is_file($this->log) ? Helper::readTaskLog($this->log, $lpos) : false;
    }
    public function writeLog($line, $console_output = true) {
        if($console_output) {echo $line."\n";}
      //  return file_put_contents($this->log, $line."\n", FILE_APPEND );
    }
}
