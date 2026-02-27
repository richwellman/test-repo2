<?php
use Vanderbilt\REDCap\Classes\Fhir\ClinicalDataPull\CachedDataRetriever;
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;

include __DIR__.'/partials/header.php';

$page = $_GET['page'] ?? null;
$perPage = $_GET['per-page'] ?? 50;
$cachedDataRetriever = new CachedDataRetriever($project_id);
$data = $cachedDataRetriever->getCachedData($record_id=null, $page, $perPage, $metadata);
$cookieName = "$project_id-CDP-decrypt";
$decrypt = ($_SESSION[$cookieName] ?? false) === true;


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $postDecrypt = $_POST['decrypt'] ?? null;
    
        // Check if the JSON payload contains "decrypt" set to true
        if (!is_null($postDecrypt)) {
            $shouldDecrypt = $postDecrypt === 'true';
            // Set the cookie with HttpOnly and no expiration (session cookie)
            if($shouldDecrypt) {
                $_SESSION[$cookieName] = true;
            } else {
                // $_SESSION[$cookieName] = null;
                unset($_SESSION[$cookieName]);
            }
            // Redirect back to the referring page
        }
        $record_ids = $_POST['record_ids'] ?? [];
        SessionDataUtils::alert($lang['cdp_dashboard_alert_success'], 'info');
    } catch(Throwable $th) {
        SessionDataUtils::alert($th->getMessage(), 'danger');
    }finally {
        redirect($_SERVER['HTTP_REFERER']);
    }
}
?>

<?php include __DIR__.'/partials/tabs.php'; ?>
<div style="max-width: 800px;">
    <div>
        <?= $lang['cdp_dashboard_cached_description'] ?>
    </div>
    <div class="alert alert-warning">
        <span class="d-block fw-bold"><?= $lang['cdp_dashboard_cached_alert'] ?></span>
    </div>
    <?php if(count($data)===0) : ?>
        <table class="table table-sm table-bordered table-hover table-striped">
            <tbody>
                <tr>
                    <td>
                        <span class="fst-italic">no records</span>
                    </td>
                </tr>
            </tbody>
        </table>
    <?php else : ?>
    <div>
        <div class="d-flex gap-2 align-items-center mb-2">
            <div id="pagination-container"></div>
            <form method="POST" action="" onsubmit="submitForm(event)">
                <input type="hidden" name="decrypt" value="<?= $decrypt ? 'false' : 'true' ?>">
                <div class="ms-2">
                    <button class="btn btn-sm btn-primary" type="submit">
                        <?php if($decrypt): ?>
                            <i class="fas fa-lock fa-fw"></i>
                            <span><?= $lang['cdp_dashboard_encrypt']?></span>
                        <?php else: ?>
                            <i class="fas fa-lock-open fa-fw"></i>
                            <span><?= $lang['cdp_dashboard_decrypt']?></span>
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
        <div class="table-container">
            <table class="table table-sm table-bordered table-hover table-striped">
                <thead>
                    <tr class="sticky">
                        <th class="sticky">record</th>
                        <th>project id</th>
                        <th>event id</th>
                        <th>map id</th>
                        <th>field name</th>
                        <th>
                            <div class="d-flex">
                                <span>cached value</span>
                                <?php if($decrypt): ?>
                                <i class="fas fa-lock-open fa-fw"></i>
                                <?php else: ?>
                                <i class="fas fa-lock fa-fw"></i>
                                <?php endif; ?>
                            </div>
                        </th>
                        <th>is record identifier</th>
                        <th>external source field name</th>
                        <th>temporal field</th>
                        <th>source timestamp</th>
                        <th>preselect strategy</th>
                        <th>form description</th>
                        <th>field order</th>
                        <th>form name</th>
                        <th>element label</th>
                        <th>element type</th>
                        <th>element enum</th>
                        <th>element note</th>
                        <th>element validation checktype</th>
                        <th>element validation type</th>
                        <th>element validation min</th>
                        <th>element validation max</th>
                        <th>adjudicated</th>
                        <th>exclude</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data as $item) : ?>
                    <tr>
                        <td class="sticky">
                            <a href="<?= $item->getLink($project_id) ?>">
                                <?= $item->record ?>
                            </a>
                        </td>
                        <td><?= $item->project_id ?></td>
                        <td><?= $item->event_id ?></td>
                        <td><?= $item->map_id ?></td>
                        <td><?= $item->field_name ?></td>
                        <td><?= $decrypt ? $item->getValue() : '********' ?></td>
                        <td><?= $item->is_record_identifier ?></td>
                        <td><?= $item->external_source_field_name ?></td>
                        <td><?= $item->temporal_field ?></td>
                        <td><?= $item->getSourceTimeStamp() ?></td>
                        <td><?= $item->preselect ?></td>
                        <td><?= $item->form_menu_description ?></td>
                        <td><?= $item->field_order ?></td>
                        <td><?= $item->form_name ?></td>
                        <td><?= $item->element_label ?></td>
                        <td><?= $item->element_type ?></td>
                        <td data-ellipsis title="<?= $item->element_enum ?>"><?= $item->element_enum ?></td>
                        <td><?= $item->element_note ?></td>
                        <td><?= $item->element_validation_checktype ?></td>
                        <td><?= $item->element_validation_type ?></td>
                        <td><?= $item->element_validation_min ?></td>
                        <td><?= $item->element_validation_max ?></td>
                        <td><?= $item->adjudicated ?></td>
                        <td><?= $item->exclude ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    <?= SessionDataUtils::getAlerts() ?>
</div>
<style>
    @import url('<?= APP_PATH_JS ?>modules/Pagination/style.css');
    @import url('<?= APP_PATH_JS ?>modules/AutoComplete/style.css');
</style>
<script type="module">
    import Pagination from '<?= getJSpath('modules/Pagination/index.js') ?>'

    const initPagination = () => {
        const paginationElement = document.querySelector('#pagination-container')
        if(!paginationElement) return
        const currentPage = <?= $metadata->page ?>;
        const totalPages = <?= $metadata->totalPages ?>;
        const perPage = <?= $metadata->perPage ?>;
        const pagination = new Pagination(paginationElement, currentPage, totalPages, perPage);
    }
    initPagination()
</script>
<script>
    function submitForm(event) {
        var form = event.target; // Get the form element from the event
        var formData = new FormData(form); // Create a FormData object from the form

        if (formData.get('decrypt') === 'true') {
            if(!confirm(`<?= $lang['cdp_dashboard_confirm_decrypt'] ?>`)) event.preventDefault()
        }
    }
</script>
<style>
table {
    width: 100%;
    border-collapse: collapse;
    max-width: 800px;
    --stickyBackground: white;
    --borderColor: rgb(255 255 255);
}

td[data-ellipsis] {
    max-width: 100px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    cursor: pointer;
}
th {
    text-transform: uppercase;
    vertical-align: top;
}


.table-container {
  max-height: calc(100vh - 300px);
  max-width: 780px;
  overflow: auto;
}
/*
STICKY ROW
Normal css box-shadow works for the header as it is a single html element
*/

tr.sticky {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--stickyBackground);
  box-shadow: 0 0 6px rgba(0,0,0,0.25);
}

/*
STICKY ROW
Normal css box-shadow works for the header as it is a single html element
*/

tr.sticky {
  position: sticky;
  top: 0;
  z-index: 1;
  background: var(--stickyBackground);
  box-shadow: 0 0 6px rgba(0,0,0,0.25);
}


/*
STICKY COLUMN
Avoid undesirable overlapping shadows by creating a faux shadow on the ::after psudo-element instead of using the css box-shadow property.
*/
th.sticky,
td.sticky {
  position: sticky;
  left: 0;
  background: var(--stickyBackground);
}

th.sticky::after,
td.sticky::after {
  content: "";
  position: absolute;
  right: -6px;
  top: 0;
  bottom: -1px;
  width: 5px;
  border-left: 1px solid var(--borderColor);
  background: linear-gradient(90deg, rgba(0,0,0,0.08) 0%, rgba(0,0,0,0) 100%);
}

th.sticky::before,
td.sticky::before {
  content: "";
  position: absolute;
  left: -6px;
  top: 0;
  bottom: -1px;
  width: 5px;
  border-right: 1px solid var(--borderColor);
  background: linear-gradient(90deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.08) 100%);
}
</style>
<?php


// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';