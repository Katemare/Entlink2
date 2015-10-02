<?

namespace Pokeliga\Entlink;

// эти файлы необходимо включить вручную, а не автоматически, потому, что собственно автоматическая загрузка классов находится в модулях, а остальное нужно для работы модулей.
include($entlink['modules_dir'].'/Basic/traits.php');
include($entlink['modules_dir'].'/Basic/Call.php');
include($entlink['modules_dir'].'/Basic/Ton.php');
include($entlink['modules_dir'].'/Basic/Promise.php');
include($entlink['modules_dir'].'/Basic/Mediator.php');
include($entlink['modules_dir'].'/Basic/Report/Report.php');
include($entlink['modules_dir'].'/Basic/Report/Report_final.php');
include($entlink['modules_dir'].'/Basic/Module/Module.php');

?>