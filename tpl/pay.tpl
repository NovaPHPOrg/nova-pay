<title id="title">支付配置 - {$title}</title>
<style id="style">
    .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    mdui-card {
        width: 100%;
    }
    .config-section {
        margin-bottom: 24px;
    }
</style>

<div id="container" class="container">
    <div class="row col-space16 p-4">
        <div class="col-xs12 title-large center-vertical mb-4">
            <mdui-icon name="payments" class="mr-2"></mdui-icon>
            <span>支付配置</span>
        </div>
        <div class="col-xs12">
            <div class="config-section">
                <form class="row col-space16" id="payForm">
                    <div class="col-md12">
                        <mdui-text-field
                            label="URL"
                            name="url"
                            type="url"
                            variant="outlined"
                            required
                            helper="支付服务器的URL"
                        ></mdui-text-field>
                    </div>
                    <div class="col-md12">
                        <mdui-text-field
                            label="客户端ID"
                            name="client_id"
                            variant="outlined"
                            helper=""
                        ></mdui-text-field>
                    </div>
                    <div class="col-md6">
                        <mdui-text-field
                            label="客户端密钥"
                            name="client_secret"
                            type="password"
                            variant="outlined"
                            helper=""
                        ></mdui-text-field>
                    </div>
                    <div class="col-md12 action-buttons">
                        <mdui-button id="savePay" icon="save" type="submit">
                            保存配置
                        </mdui-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script id="script">
    window.pageLoadFiles = [
        'Form'
    ];

    window.pageOnLoad = function (loading) {

        $.form.manage("/pay/config","#payForm")
        // 处理渠道状态开关变化
        window.pageOnUnLoad = function () {
            // 页面卸载时的清理工作
        };
    };
</script>