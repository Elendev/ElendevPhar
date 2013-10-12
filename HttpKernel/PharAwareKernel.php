<?php
namespace Elendev\Phar\HttpKernel;
use Symfony\Component\HttpKernel\Kernel;
use \Phar;

abstract class PharAwareKernel extends Kernel {
	
	public function getCacheDir() {
		if(Phar::running()){
			return dirname(Phar::running(false)) . '/cache/' . $this->environment;
		} else {
			return $this->rootDir . "/cache/" . $this->environment;
		}
	}
	
	public function getLogDir() {
		if(Phar::running()){
			return dirname(Phar::running(false)) . '/logs/' . $this->environment;
		} else {
			return $this->rootDir . "/logs/" . $this->environment;
		}
	}
}
