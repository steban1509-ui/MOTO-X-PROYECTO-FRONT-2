<?php
// Si el proyecto se copia completo en htdocs (XAMPP/WAMP), esta página
// envía al visitante a la carpeta pública, que es la raíz real del sitio.
header('Location: public/');
exit;
