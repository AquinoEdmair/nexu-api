<?php

return [
    /*
     | Email adicional que recibe BCC de todas las alertas admin.
     | Útil para un buzón compartido del equipo (ej. ops@nexu.com).
     | Dejar vacío para no enviar BCC.
     */
    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL', ''),
];
