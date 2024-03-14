<style>
    .card-header {
        background-color: #007bff;
        color: #fff;
    }

    .list-group-item {
        border-left: 3px solid #007bff;
    }

    .vm-detail-highlight {
        background-color: #f8f9fa;
        border-left: 4px solid #007bff;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 5px;
    }

    .btn-custom-upgrade {
        background-color: #28a745;
        border-color: #28a745;
        color: #ffffff;
    }

    .btn-custom-upgrade:hover {
        background-color: #218838;
        border-color: #1e7e34;
    }
    .status-indicator {
        width: 20px;
        height: 20px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 10px;
    }

    .status-indicator.running {
        background-color: #28a745; /* Yeşil */
    }

    .status-indicator.stopped {
        background-color: #dc3545; /* Kırmızı */
    }

    .vm-detail-highlight {
        display: flex;
        align-items: center;
    }
    
.vm-status-circle {
    width: 200px;
    height: 200px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    margin: 20px auto;
    position: relative;
    overflow: hidden;
}
.running {
    background-color:#007bff;
}
.starting {
    background-color:#ffc107;
}
.stopped {
    background-color:#dc3545;
}
.stopping {
    background-color:#ffc107;
}
.shutdown {
    background-color:#ffc107;
}
.resetting {
    background-color:#ffc107;
}
.rebooting {
    background-color:#ffc107;
}
.rebuild {
    background-color:#ffc107;
}
.eroor {
    background-color:#dc3545;
}
.bubble {
    position: absolute;
    bottom: 100%;
    background-color: rgba(255, 255, 255, 0.6);
    border-radius: 50%;
}

@keyframes rise {
    0% { transform: scale(0); bottom: -100px; opacity: 0; }
    50% { opacity: 1; }
    100% { bottom: 250px; transform: scale(1); opacity: 0; }
}

.bubble:nth-child(1) {
    left: 20%;
    width: 40px;
    height: 40px;
    animation: rise 4s infinite ease-in;
    animation-delay: 0s;
}

.bubble:nth-child(2) {
    left: 50%;
    width: 30px;
    height: 30px;
    animation: rise 3s infinite ease-in;
    animation-delay: 1s;
}

.bubble:nth-child(3) {
    left: 70%;
    width: 20px;
    height: 20px;
    animation: rise 5s infinite ease-in;
    animation-delay: 2s;
}

.bubble:nth-child(4) {
    left: 40%;
    width: 50px;
    height: 50px;
    animation: rise 6s infinite ease-in;
    animation-delay: 3s;
}

.bubble:nth-child(5) {
    left: 80%;
    width: 25px;
    height: 25px;
    animation: rise 7s infinite ease-in;
    animation-delay: 4s;
}
.tab-pane > .row:not(.module-client-area) {
        display: none;
    }
    .nav-tabs {
    display:none;
   }
   .tab-pane > .row:not(.module-client-area) {
        display: none;
    }
    .tab-pane.fade.show.active > .card:first-child {
        display: none;
    }
</style>

<h2 class="mb-4">VM IPs Management</h2>

<div class="card mb-3">
  <div class="card-header">Reverse DNS Settings</div>
   <div class="card-body">
    <form method="post">
    <input type="hidden" name="customAction" value="updateRdns">
    <input type="hidden" name="id" value="{$serviceid}" />
    <table class="table">
        <thead>
            <tr>
                <th>IP Address</th>
                <th>Reverse DNS (PTR Record)</th>
            </tr>
        </thead>
        <tbody>
            {foreach from=$getRdns.rdns item=rdns}
            <tr>
                <td><b>{$rdns.ipaddress}</b></td>
                <td>
                    <input type="hidden" name="ipaddress[]" value="{$rdns.ipaddress}">
                    <input type="text" name="ptrcontent[]" value="{$rdns.ptrcontent}" class="form-control">
                </td>
            </tr>
            {/foreach}
        </tbody>
    </table>
    <div class="text-right">
        <button type="submit" class="btn btn-primary">Update All</button>
    </div>
    </form>
  </div>
</div>

<hr>

<div class="row"><div class="col-sm-4"><a href="clientarea.php?action=productdetails&id={$serviceid}" class="btn btn-default btn-block"><i class="fa fa-arrow-circle-left"></i> Back to VM Overview</a></div></div>

<script>
function confirmDelete() {
    return confirm("Are you sure you want to delete this network interface?");
}
</script>
