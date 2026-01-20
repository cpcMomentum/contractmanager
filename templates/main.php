<?php

declare(strict_types=1);

$isAdmin = $_['isAdmin'] ?? false;

?>

<div id="app-content-contractmanager" class="app-contractmanager" data-is-admin="<?php echo $isAdmin ? 'true' : 'false'; ?>"></div>
