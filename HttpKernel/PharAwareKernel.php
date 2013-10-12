<?php
namespace Elendev\Phar\HttpKernel;
use Symfony\Component\HttpKernel\Kernel;
use \Phar;

abstract class PharAwareKernel extends Kernel {
	
	public function getCacheDir() {
		if(Phar::running()){
			if(defined('ELENDEV_PHAR_CACHE_DIR')) {
				$baseDir = ELENDEV_PHAR_CACHE_DIR;
			} else {
				$baseDir = dirname(Phar::running(false)) . '/cache/' . basename(Phar::running(false), ".phar") . "/";
			}
			
			return $baseDir . "/" . $this->environment;
		} else {
			return $this->rootDir . "/cache/" . $this->environment;
		}
	}
	
	public function getLogDir() {
		if(Phar::running()){
			
			if(defined('ELENDEV_PHAR_LOG_DIR')) {
				$baseDir = ELENDEV_PHAR_LOG_DIR;
			} else {
				$baseDir = dirname(Phar::running(false)) . '/logs/' . basename(Phar::running(false), ".phar") . "/";
			}
			
			return $baseDir . "/" . $this->environment;
			
		} else {
			return $this->rootDir . "/logs/" . $this->environment;
		}
	}
}
