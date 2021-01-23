<?php
if (isBot()) {
    return false;
}
$refl = new ReflectionClass('SocketMessageType');
$obj = AVideoPlugin::getDataObjectIfEnabled('Socket');
if (!empty($obj->debugAllUsersSocket) || (User::isAdmin() && !empty($obj->debugSocket))) {
    $socket_info_container_class = '';
    $socket_info_container_top = 60;
    $socket_info_container_left = 50;
    if ($_COOKIE['socketInfoMinimized']) {
        $socket_info_container_class = 'socketMinimized';
    }
    if ($_COOKIE['socketInfoPositionTop']) {
        $socket_info_container_top = $_COOKIE['socketInfoPositionTop'];
    }
    if ($_COOKIE['socketInfoPositionLeft']) {
        $socket_info_container_left = $_COOKIE['socketInfoPositionLeft'];
    }
    ?>
    <style>
        #socket_info_container div{
            text-shadow: 0 0 2px #FFF;
            padding: 5px;
            font-size: 11px;
        }
        #socket_info_container div.socketItem span{
            float: right;
            color: #fff;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            background-color: #777;
            border-radius: 10px;
            font-size: 11px;
            margin-left: 10px;
            text-shadow: none;
            padding: 2px 3px;
        }
        #socket_info_container{
            border-radius: 5px;
            border: 2px solid #777;
            position: fixed; 
            top: <?php echo $socket_info_container_top; ?>px; 
            left: <?php echo $socket_info_container_left; ?>px; 

            background-color: rgba(255,255,255,0.7);
            color: #000;

            -webkit-transition: background  0.5s ease-in-out;
            -moz-transition: background  0.5s ease-in-out;
            -ms-transition: background  0.5s ease-in-out;
            -o-transition: background  0.5s ease-in-out;
            transition: background  0.5s ease-in-out;
            opacity: 1;
            cursor: move;

            -moz-box-shadow:    0 0 10px #000000;
            -webkit-box-shadow: 0 0 10px #000000;
            box-shadow:         0 0 10px #000000;
            z-index: 9999;
            max-width: 300px;

        }
        #socket_info_container:hover{
            opacity: 1;
            background-color: rgba(255,255,255,1);
        }

        #socket_info_container.disconnected div{
            color: #00000077;
        }
        #socket_info_container.disconnected .socketItem span{
            opacity: 0.5;
        }

        #socketBtnMaximize{
            display: none;
        }
        #socket_info_container.socketMinimized .socketItem, #socket_info_container.socketMinimized #socketBtnMinimize{
            display: none;
        }
        #socket_info_container.socketMinimized #socketBtnMaximize{
            display: block;
        }
        .socketTitle, .socketTitle span{
            text-align: center;
            font-size: 14px;
            width: 100%;
        }
        #socketUsersURI{
            max-height: 300px;
            overflow: auto;
        }
        #socketUsersURI img{
            height: 20px;
            width: 20px;
            margin: 2px 5px;
            display: inline;
        }
    </style>

    <div id="socket_info_container" class="socketStatus disconnected <?php echo $socket_info_container_class; ?>" >
        <div class="socketTitle">
            <div class="pull-left">
                <?php
                echo getSocketConnectionLabel();
                ?>
            </div>
            <div class="pull-right">
                <button class="btn btn-xs" id="socketBtnMinimize">
                    <i class="fas fa-window-minimize"></i>
                </button>
                <button class="btn btn-xs maximize" id="socketBtnMaximize">
                    <i class="far fa-window-maximize"></i>
                </button>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="socketItem" ><i class="fas fa-user"></i> Your User ID <span class="socket_users_id">0</span></div>
        <div class="socketItem" ><i class="fas fa-id-card"></i> Socket ResourceId <span class="socket_resourceId">0</span></div>
        <div class="socketItem" ><i class="fas fa-network-wired"></i> Total Different Devices <span class="total_devices_online">0</span></div>
        <div class="socketItem" ><i class="fas fa-users"></i> Total Users Online <span class="total_users_online">0</span></div>
        <div class="socketItem" ><i class="far fa-play-circle"></i> Users online on same video as you <span class="total_on_same_video">0</span></div>
        <div class="socketItem" ><i class="fas fa-podcast"></i> Users online on same live as you <span class="total_on_same_live">0</span></div>
        <div class="socketItem" ><i class="fas fa-podcast"></i> Users online on same live link as you <span class="total_on_same_livelink">0</span></div>
        <hr>
        <div class="socketItem" id="socketUsersURI"></div>
    </div>
    <script>
        $(document).ready(function () {
            if (typeof $("#socket_info_container").draggable === 'function') {
                $("#socket_info_container").draggable({
                    stop: function (event, ui) {
                        var currentPos = $(this).position();
                        Cookies.set('socketInfoPositionTop', currentPos.top, {
                            path: '/',
                            expires: 365
                        });
                        Cookies.set('socketInfoPositionLeft', currentPos.left, {
                            path: '/',
                            expires: 365
                        });
                    }
                });
            }
            $("#socketBtnMinimize").click(function () {
                socketInfoMinimize();
            });
            $("#socketBtnMaximize").click(function () {
                socketInfoMaximize();
            });
            checkSocketInfoPosition();
        });

        function socketInfoMinimize() {
            $("#socket_info_container").addClass('socketMinimized');

            Cookies.set('socketInfoMinimized', 1, {
                path: '/',
                expires: 365
            });
        }
        function socketInfoMaximize() {
            $("#socket_info_container").removeClass('socketMinimized');
            Cookies.set('socketInfoMinimized', 0, {
                path: '/',
                expires: 365
            });
        }

        function checkSocketInfoPosition() {
            var currentPos = $('#socket_info_container').position();
            var maxLeft = $(window).width() - $('#socket_info_container').width();
            var maxTop = $(window).height() - $('#socket_info_container').height();

            if (currentPos.top < 60 || currentPos.left < 60 || currentPos.top > maxTop || currentPos.left > maxLeft) {
                $('#socket_info_container').css('top', '60px');
                $('#socket_info_container').css('left', '60px');
            }
        }


    </script>
    <?php
}
?>
<script>
    var webSocketServerVersion = '<?php echo Socket::getServerVersion(); ?>';
    var webSocketToken = '<?php echo getEncryptedInfo(0); ?>';
    var webSocketURL = '<?php echo Socket::getWebSocketURL(); ?>';
    var webSocketTypes = <?php echo json_encode($refl->getConstants()); ?>;
</script>
<script src="<?php echo $global['webSiteRootURL']; ?>plugin/Socket/script.js?<?php echo filectime($global['systemRootPath'] . 'plugin/Socket/script.js'); ?>" type="text/javascript"></script>
