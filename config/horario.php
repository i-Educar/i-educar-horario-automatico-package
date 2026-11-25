<?php

return [
    'public' => [
        'aparecer_na_home' => false,
        'titulo' => 'Quadro de horários',
        'ordem' => 64,
        'url' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Quadro de horários — geração municipal
    |--------------------------------------------------------------------------
    |
    | usar_servidores_nucleo   Seed inicial a partir de servidor/turma.
    | detectar_choques         Bloqueia alocação/movimento com conflito.
    | seed_demo_se_vazio       Demo quando poc_registro vazio.
    | geracao_passos           Itens processados por “tick” da geração.
    |
    */
    'usar_servidores_nucleo' => (bool) env('HORARIO_USAR_SERVIDORES_NUCLEO', true),
    'detectar_choques' => (bool) env('HORARIO_DETECTAR_CHOQUES', true),
    'seed_demo_se_vazio' => (bool) env('HORARIO_SEED_DEMO', false),
    'geracao_passos' => (int) env('HORARIO_GERACAO_PASSOS', 60),
];
