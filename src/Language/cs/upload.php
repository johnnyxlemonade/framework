<?php

declare(strict_types=1);

return [
    'payload_missing' => 'Chybí payload nahrávaného souboru.',
    'file_empty' => 'Nahraný soubor je prázdný.',
    'file_too_large' => 'Nahraný soubor překračuje maximální povolenou velikost.',
    'mime_not_detected' => 'Nepodařilo se zjistit MIME typ nahraného souboru.',
    'mime_not_allowed' => 'MIME typ "{mime}" není povolen.',
    'extension_not_allowed' => 'Přípona souboru "{extension}" není povolena.',
    'tmp_not_valid' => 'Dočasný nahraný soubor není platný.',

    'error_too_large' => 'Nahraný soubor je příliš velký.',
    'error_partial' => 'Soubor byl nahrán pouze částečně.',
    'error_no_file' => 'Nebyl nahrán žádný soubor.',
    'error_no_tmp_dir' => 'Chybí dočasná složka pro upload.',
    'error_cant_write' => 'Soubor se nepodařilo zapsat na disk.',
    'error_stopped_by_extension' => 'Nahrávání souboru bylo zastaveno rozšířením PHP.',
    'error_unknown' => 'Neznámá chyba uploadu.',

    'target_directory_missing' => 'Cílový adresář pro upload není nastaven.',
    'create_target_directory_failed' => 'Nelze vytvořit cílový adresář: {directory}',
    'move_failed' => 'Nahraný soubor se nepodařilo přesunout.',
    'file_size_not_detected' => 'Nepodařilo se zjistit velikost uloženého souboru.',
    'filename_generation_failed' => 'Nepodařilo se vygenerovat název uloženého souboru.',

    'image_not_valid' => 'Nahraný soubor není validní obrázek.',
    'image_min_width' => 'Šířka obrázku musí být alespoň {width} px.',
    'image_max_width' => 'Šířka obrázku může být maximálně {width} px.',
    'image_min_height' => 'Výška obrázku musí být alespoň {height} px.',
    'image_max_height' => 'Výška obrázku může být maximálně {height} px.',
    'image_mime_not_supported' => 'MIME typ obrázku "{mime}" není podporován.',
    'gd_not_available' => 'Obrázek nelze bezpečně zpracovat, protože není dostupné rozšíření GD.',
    'stored_image_not_readable' => 'Uložený soubor není čitelný obrázek.',
    'image_decode_failed' => 'Nahraný obrázek se nepodařilo dekódovat.',
    'image_reencode_failed' => 'Překódovaný obrázek se nepodařilo uložit.',

    'file_profile_not_configured' => 'Profil uploadu souborů "{profile}" není nakonfigurován.',
    'file_profile_missing_target_directory' => 'Profil uploadu souborů "{profile}" nemá nastavený target_directory.',
    'image_profile_not_configured' => 'Profil uploadu obrázků "{profile}" není nakonfigurován.',
    'image_profile_missing_target_directory' => 'Profil uploadu obrázků "{profile}" nemá nastavený target_directory.',
];
