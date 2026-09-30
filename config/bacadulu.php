<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BacaDulu Public Contact
    |--------------------------------------------------------------------------
    |
    | Nomor call center menggunakan environment variable existing:
    |
    | BACA_CALL_CENTER="+62 851-7957-7876"
    |
    | Nomor ini digunakan sebagai label yang tampil di halaman.
    | Normalisasi ke format wa.me dilakukan pada view footer.
    |
    */

    'contact_email' => env(
        'BACADULU_CONTACT_EMAIL',
        'admnbacadulu.net@gmail.com'
    ),

    'call_center' => env(
        'BACA_CALL_CENTER',
        '+62 851-7957-7876'
    ),

];