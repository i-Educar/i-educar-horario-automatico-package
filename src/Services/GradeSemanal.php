<?php

namespace iEducar\Packages\BuritiHorarioAutomatico\Services;

final class GradeSemanal
{
    /** @param list<array{dia:int,componente:string,inicio:string,fim:string}> $slots */
    public static function cargaMinutos(array $slots): int {
        $m=0; foreach($slots as $s){
            [$hi,$mi]=array_map('intval', explode(':',$s['inicio']));
            [$hf,$mf]=array_map('intval', explode(':',$s['fim']));
            $m += ($hf*60+$mf)-($hi*60+$mi);
        } return $m;
    }
}
