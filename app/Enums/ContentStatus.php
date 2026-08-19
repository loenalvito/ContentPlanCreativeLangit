<?php
namespace App\Enums;
enum ContentStatus:string{
 case Planned='planned';case InProduction='in_production';case Review='review';case Approved='approved';case Scheduled='scheduled';case Published='published';
 public function label():string{return match($this){self::Planned=>'Planned',self::InProduction=>'In Production',self::Review=>'Review',self::Approved=>'Approved',self::Scheduled=>'Scheduled',self::Published=>'Published'};}
 public function allowedTargets():array{return array_values(array_filter(self::cases(),fn($status)=>$status!==$this));}
 public function canTransitionTo(self $target):bool{return $this!==$target;}
}
