<?php
return [
  // Usa la misma versión que en tu ejemplo C# (v17.0). Si luego subes a v20.0, cambia solo esta línea.
  'graph_url'       => 'https://graph.facebook.com/v17.0',

  // Sale del path de tu URL: .../v17.0/**335894526282507**/messages
  'phone_number_id' => '335894526282507',

  // Es el token largo que pegaste (te recomiendo moverlo a variable de entorno en prod).
  'access_token'    => 'EAAGacaATjwEBOZBgqhohcVk1ZBGEAbiTl7i86qESvSPjdllaomwzIG7LmOOvyTFpzyIlXX6dtTYTVTLLuw6SjaLoh2rec07I8qu1nGNYSVZAmQTGNa3QCQjujTqfd7QuLLwFNQllnX2z1V7JvToDhEi5KVqUWXHSqgSETvGyU7S2SN2fpXW0NpQaRI48pwZAgGS7A1BQMjLl5ZBjy',

  // Idioma por defecto para plantillas (si usas template messages).
  'default_lang'    => 'en_US',

  // Prefijo país para normalizar teléfonos (México).
  'country_code'    => '+52',
];
