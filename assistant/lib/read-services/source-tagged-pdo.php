<?php
declare(strict_types=1);
namespace JenangMcp;
/** Keeps sanitized database identity on failures inside shared multi-source helpers. */
final class BusinessReadPDO extends \PDO {
 public function __construct(private \PDO $source,private string $database){if(!in_array($database,['analytics','sku','partner'],true)||!$source->inTransaction())throw new \LogicException('Active reporting source required.');}
 private function attempt(callable $call):mixed{try{return $call();}catch(ReportingSourceUnavailable $error){throw $error;}catch(\Throwable $error){throw new ReportingSourceUnavailable($this->database,sourceFailure($error));}}
 public function prepare(string $query,array $options=[]):\PDOStatement|false{return $this->attempt(function()use($query,$options){$statement=$this->source->prepare($query,$options);if(!$statement)throw new \RuntimeException('Source preparation failed.');return new BusinessReadStatement($statement,$this->database);});}
 public function query(string $query,?int $fetchMode=null,mixed ...$args):\PDOStatement|false{return $this->attempt(function()use($query,$fetchMode,$args){$statement=$fetchMode===null?$this->source->query($query):$this->source->query($query,$fetchMode,...$args);if(!$statement)throw new \RuntimeException('Source query failed.');return new BusinessReadStatement($statement,$this->database);});}
 public function inTransaction():bool{return $this->source->inTransaction();}
 public function getAttribute(int $attribute):mixed{return $this->attempt(fn()=>$this->source->getAttribute($attribute));}
 public function rollBack():bool{return $this->attempt(fn()=>$this->source->rollBack());}
 public function exec(string $statement):int|false{throw new ReportingSourceUnavailable($this->database,['reason'=>'read_only_unavailable']);}
 public function beginTransaction():bool{throw new ReportingSourceUnavailable($this->database,['reason'=>'read_only_unavailable']);}
 public function commit():bool{throw new ReportingSourceUnavailable($this->database,['reason'=>'read_only_unavailable']);}
 public function setAttribute(int $attribute,mixed $value):bool{throw new ReportingSourceUnavailable($this->database,['reason'=>'read_only_unavailable']);}
}
final class BusinessReadStatement extends \PDOStatement {
 public function __construct(private \PDOStatement $source,private string $database){}
 private function attempt(callable $call):mixed{try{return $call();}catch(ReportingSourceUnavailable $error){throw $error;}catch(\Throwable $error){throw new ReportingSourceUnavailable($this->database,sourceFailure($error));}}
 public function execute(?array $params=null):bool{return $this->attempt(fn()=>$this->source->execute($params));}
 public function fetch(int $mode=\PDO::FETCH_DEFAULT,int $cursorOrientation=\PDO::FETCH_ORI_NEXT,int $cursorOffset=0):mixed{return $this->attempt(fn()=>$this->source->fetch($mode,$cursorOrientation,$cursorOffset));}
 public function fetchAll(int $mode=\PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->attempt(fn()=>$this->source->fetchAll($mode,...$args));}
 public function fetchColumn(int $column=0):mixed{return $this->attempt(fn()=>$this->source->fetchColumn($column));}
 public function bindValue(string|int $param,mixed $value,int $type=\PDO::PARAM_STR):bool{return $this->attempt(fn()=>$this->source->bindValue($param,$value,$type));}
 public function bindParam(string|int $param,mixed &$var,int $type=\PDO::PARAM_STR,int $maxLength=0,mixed $driverOptions=null):bool{return $this->attempt(function()use($param,&$var,$type,$maxLength,$driverOptions){return $this->source->bindParam($param,$var,$type,$maxLength,$driverOptions);});}
 public function rowCount():int{return $this->attempt(fn()=>$this->source->rowCount());}
 public function setFetchMode(int $mode,mixed ...$args):true{return $this->attempt(fn()=>$this->source->setFetchMode($mode,...$args));}
 public function getIterator():\Iterator{try{yield from $this->source;}catch(\Throwable $error){if($error instanceof ReportingSourceUnavailable)throw $error;throw new ReportingSourceUnavailable($this->database,sourceFailure($error));}}
}
