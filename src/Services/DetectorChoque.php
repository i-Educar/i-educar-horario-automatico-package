<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

final class DetectorChoque
{
    /** @param list<array{professor_id:int,dia:int,inicio:string,fim:string}> $aulas */
    public static function choques(array $aulas): array {
        $out=[]; $n=count($aulas);
        for($i=0;$i<$n;$i++) for($j=$i+1;$j<$n;$j++){
            $a=$aulas[$i]; $b=$aulas[$j];
            if($a['professor_id']!==$b['professor_id']||$a['dia']!==$b['dia']) continue;
            if($a['inicio'] < $b['fim'] && $b['inicio'] < $a['fim']) $out[]=[$i,$j];
        }
        return $out;
    }
}
