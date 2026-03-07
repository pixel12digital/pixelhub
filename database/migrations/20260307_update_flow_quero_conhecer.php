<?php

use PixelHub\Core\DB;

$db = DB::getConnection();

$db->prepare("
    UPDATE chatbot_flows
    SET
        response_message  = 'Perfeito! 😊 Um de nossos consultores vai entrar em contato em breve para te mostrar como o ImobSites funciona na prática.\n\nEnquanto isso, fique à vontade para dar uma olhada no nosso site: https://imobsites.com.br/',
        forward_to_human  = 1,
        next_buttons      = NULL,
        updated_at        = NOW()
    WHERE id = 1
")->execute();

echo "Migration OK: flow 'Quero conhecer' (ID 1) atualizado com mensagem de handoff\n";
