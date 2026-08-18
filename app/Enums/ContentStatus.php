<?php
namespace App\Enums;
enum ContentStatus:string{
 case Planned='planned';case InProduction='in_production';case Review='review';case Approved='approved';case Scheduled='scheduled';case Published='published';
 public function label():string{return match($this){self::Planned=>'Planned',self::InProduction=>'In Production',self::Review=>'Review',self::Approved=>'Approved',self::Scheduled=>'Scheduled',self::Published=>'Published'};}
 public function allowedTargets(bool $rollback=false):array{return match($this){self::Planned=>[self::InProduction],self::InProduction=>[self::Planned,self::Review],self::Review=>[self::InProduction,self::Approved],self::Approved=>[self::Review,self::Scheduled],self::Scheduled=>[self::Approved,self::Published],self::Published=>$rollback?[self::Scheduled]:[]};}
 public function canTransitionTo(self $target,bool $rollback=false):bool{return $this===$target||in_array($target,$this->allowedTargets($rollback),true);}
}
