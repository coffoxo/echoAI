<?php
/**
 * echoAI - 通用AI写作助手
 *
 * @package echoAI
 * @author coffox
 * @version 1.0.0
 * @link https://github.com/coffoxo/echoAI
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class echoAI_Plugin implements Typecho_Plugin_Interface
{
    const PROXY_ACTION_NAME = 'echoai-proxy';
    const TEST_ACTION_NAME  = 'echoai-test';

    public static function activate()
    {
        Helper::addAction(self::PROXY_ACTION_NAME, __CLASS__);
        Helper::addAction(self::TEST_ACTION_NAME,  __CLASS__);
        Typecho_Plugin::factory('admin/write-post.php')->bottom = array(__CLASS__, 'renderEditorUI');
        return "echoAI插件启用成功，请配置API参数。";
    }

    public static function deactivate()
    {
        return "echoAI插件已禁用";
    }

    public static function config(Typecho_Widget_Helper_Form $form)
    {

        $div = new Typecho_Widget_Helper_Layout();
        $div->html('<div style="background:#f8f9fa; padding:15px; border-radius:5px; margin-bottom:20px;"><h5>API 通用配置</h5><p>本插件通过服务器代理请求，确保您的API密钥不会泄露到浏览器。</p><p>1. <strong>API基础地址 (Base URL)</strong>: 填写服务商提供的完整基础地址，应包含版本号（如`v1`），但**不应**包含具体的接口名（如`/chat/completions`）。<br>    - OpenAI 示例: <code>https://api.openai.com/v1</code><br>    - DeepSeek 示例: <code>https://api.deepseek.com/v1</code><br>    - 自定义代理示例: <code>https://your-proxy.com/api/v1beta</code></p></div>');
        $div->render();
        
        $baseUrl = new Typecho_Widget_Helper_Form_Element_Text('baseUrl', null, 'https://api.deepseek.com/v1', _t('API基础地址 (Base URL)'), _t('请在此处输入包含版本号的API基础地址'));
        $form->addInput($baseUrl->addRule('required', _t('API基础地址不能为空')));

        $apiKey = new Typecho_Widget_Helper_Form_Element_Text('apiKey', null, null, _t('API密钥'), _t('在此处输入您的API密钥'));
        $form->addInput($apiKey->addRule('required', _t('API密钥不能为空')));
        
        $model = new Typecho_Widget_Helper_Form_Element_Text('model', null, 'deepseek-chat', _t('模型名称'), _t('根据您的API服务商，填写可用的模型ID'));
        $form->addInput($model->addRule('required', _t('模型名称不能为空')));
        
        $temperature = new Typecho_Widget_Helper_Form_Element_Text('temperature', null, '0.7', _t('原创性 (0.1-1.0)'), _t('值越高生成内容越有原创性，建议0.5-0.8'));
        $form->addInput($temperature);

        $security = Typecho_Widget::widget('Widget_Security');
        $testActionUrl = Typecho_Common::url('action/' . self::TEST_ACTION_NAME, Helper::options()->index);
        $token = $security->getToken($testActionUrl);

        $testHtml = <<<HTML
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #E9E9E9;">
                <button type="button" id="echoai-test-btn" class="btn primary">测试API连接</button>
                <span id="echoai-test-result" style="margin-left: 10px; font-weight: bold;"></span>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const testBtn = document.getElementById('echoai-test-btn');
                if (!testBtn) return;

                testBtn.addEventListener('click', function() {
                    const resultSpan = document.getElementById('echoai-test-result');
                    resultSpan.textContent = '';
                    const baseUrl = document.querySelector('input[name="baseUrl"]').value;
                    const apiKey = document.querySelector('input[name="apiKey"]').value;
                    const model = document.querySelector('input[name="model"]').value;
                    
                    resultSpan.style.color = '#ffc107';
                    resultSpan.textContent = '正在测试...';
                    testBtn.disabled = true;

                    fetch('{$testActionUrl}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            baseUrl: baseUrl,
                            apiKey: apiKey,
                            model: model,
                            token: '{$token}'
                        })
                    })
                    .then(res => {
                        if (!res.ok) {
                            return res.json().then(err => { throw new Error(err.message || '服务器返回了不可读的错误 (HTTP ' + res.status + ')'); });
                        }
                        return res.json();
                    })
                    .then(data => {
                        if (data.status === 'success') {
                            resultSpan.style.color = '#28a745';
                            resultSpan.textContent = '成功: ' + data.message;
                        } else {
                            resultSpan.style.color = '#dc3545';
                            resultSpan.textContent = '失败: ' .concat(data.message);
                        }
                    })
                    .catch(err => {
                        resultSpan.style.color = '#dc3545';
                        resultSpan.textContent = '请求失败: ' .concat(err.message);
                    })
                    .finally(() => {
                        testBtn.disabled = false;
                    });
                });
            });
            </script>
HTML;
        $layout = new Typecho_Widget_Helper_Layout();
        $layout->html($testHtml);
        $form->addItem($layout);
    }
    
    public static function renderEditorUI()
    {
        $security = Typecho_Widget::widget('Widget_Security');
        $proxyUrl = Typecho_Common::url('action/' . self::PROXY_ACTION_NAME, Helper::options()->index);
        $proxyToken = $security->getToken($proxyUrl);

        echo <<<HTML
            <section id="echoai-main-section" class="typecho-post-option collapsed">
                <div id="echoai-toggle-header" class="typecho-label">
                    <span id="echoai-toggle-icon">▶</span>
                    echoAI 写作助手
                </div>

                <div id="echoai-container" style="display: none;">
                    <style>
                        #echoai-toggle-header {
                            cursor: pointer;
                            user-select: none;
                            display: flex;
                            align-items: center;
                            padding: 5px 0;
                            gap: 8px;
                            transition: color 0.2s ease-in-out;
                        }
                        
                        #echoai-toggle-header:hover {
                            color: #467B96;
                        }

                        #echoai-toggle-icon {
                            font-size: 10px;
                            opacity: 0.6; 
                            transition: transform 0.2s ease-in-out;
                            transform: rotate(0deg);
                        }

                        #echoai-main-section:not(.collapsed) #echoai-toggle-icon {
                            transform: rotate(90deg);
                        }

                        #echoai-container {
                            margin-top: 15px;
                            display: grid;
                            grid-template-columns: 1fr;
                            gap: 12px;
                        }

                        .echoai-helper-text {
                            font-size: 0.9em;
                            opacity: 0.6;
                            font-style: italic;
                            font-weight: normal;
                        }
                        #echoai-container ::placeholder {
                           font-size: 0.95em;
                           opacity: 0.6;
                           font-style: italic;
                        }
                        
                        #echoai-container .text, #echoai-container .textarea { width: 100%; box-sizing: border-box; }
                        #echoai-controls { display: flex; gap: 10px; align-items: center; }
                        #echoai-generate-btn { flex-shrink: 0; }
                        #echoai-status-msg { font-weight: bold; }
                        #echoai-result-label { margin-top: 10px; font-weight: bold; border-top: 1px solid #E9E9E9; padding-top: 15px; }
                        #echoai-result-area { width: 100%; min-height: 150px; background-color: #f8f9fa; border: 1px solid #E9E9E9; border-radius: 4px; padding: 10px; white-space: pre-wrap; word-wrap: break-word; font-family: Menlo, Monaco, Consolas, "Courier New", monospace; font-size: 13px; line-height: 1.6; box-sizing: border-box; }
                    </style>

                    <div>
                        <label for="echoai-keywords">关键词 <span class="echoai-helper-text">(必填，多个用逗号隔开)</span></label>
                        <input type="text" id="echoai-keywords" class="text" placeholder="例如：人工智能, 未来发展, 伦理道德">
                    </div>
                    <div>
                        <label for="echoai-prompt">具体要求 <span class="echoai-helper-text">(选填)</span></label>
                        <textarea id="echoai-prompt" class="textarea" rows="3" placeholder="例如：写一篇800字左右的科技评论，风格要求客观严谨。"></textarea>
                    </div>
                    <div id="echoai-controls">
                        <button type="button" id="echoai-generate-btn" class="btn primary">生成内容</button>
                        <span id="echoai-status-msg"></span>
                    </div>
                    <label id="echoai-result-label" style="display:none;">生成结果预览：</label>
                    <div id="echoai-result-area" style="display:none;"></div>
                </div>
            </section>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const pluginSection = document.getElementById('echoai-main-section');
                const editorTextarea = document.getElementById('text');
                if (pluginSection && editorTextarea) {
                    const editorWrapper = editorTextarea.parentNode;
                    editorWrapper.parentNode.insertBefore(pluginSection, editorWrapper.nextSibling);
                    pluginSection.style.display = 'block';
                }
                const toggleHeader = document.getElementById('echoai-toggle-header');
                const pluginContainer = document.getElementById('echoai-container');
                toggleHeader.addEventListener('click', function() {
                    pluginSection.classList.toggle('collapsed');
                    const isCollapsed = pluginSection.classList.contains('collapsed');
                    if (isCollapsed) {
                        pluginContainer.style.display = 'none';
                    } else {
                        pluginContainer.style.display = 'grid';
                    }
                });
                const generateBtn = document.getElementById('echoai-generate-btn');
                const statusSpan = document.getElementById('echoai-status-msg');
                const keywordsInput = document.getElementById('echoai-keywords');
                const promptInput = document.getElementById('echoai-prompt');
                const resultLabel = document.getElementById('echoai-result-label');
                const resultArea = document.getElementById('echoai-result-area');
                const mainEditor = document.getElementById('text');
                generateBtn.addEventListener('click', function() {
                    if (!keywordsInput.value.trim()) {
                        statusSpan.style.color = '#dc3545';
                        statusSpan.textContent = '错误：关键词不能为空！';
                        return;
                    }
                    statusSpan.style.color = '#007bff';
                    statusSpan.textContent = '正在联系AI思考...';
                    generateBtn.disabled = true;
                    resultArea.style.display = 'none';
                    resultLabel.style.display = 'none';
                    fetch('{$proxyUrl}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ keywords: keywordsInput.value, prompt: promptInput.value, token: '{$proxyToken}' })
                    })
                    .then(res => {
                        if (!res.ok) { return res.json().then(err => { throw new Error(err.message || `服务器错误 (HTTP \${res.status})`); }); }
                        return res.json();
                    })
                    .then(data => {
                        if (data.status === 'success' && data.content) {
                            statusSpan.style.color = '#28a745';
                            statusSpan.textContent = '生成成功！内容已追加到文章末尾。';
                            resultLabel.style.display = 'block';
                            resultArea.textContent = data.content;
                            resultArea.style.display = 'block';
                            const currentContent = mainEditor.value;
                            const separator = currentContent.trim().length > 0 ? '\\n\\n' : '';
                            mainEditor.value += separator + data.content;
                        } else {
                            throw new Error(data.message || '返回数据格式不正确');
                        }
                    })
                    .catch(err => {
                        statusSpan.style.color = '#dc3545';
                        statusSpan.textContent = '生成失败: ' + err.message;
                    })
                    .finally(() => {
                        generateBtn.disabled = false;
                    });
                });
            });
            </script>
HTML;
    }
   
    public function execute()
    {
        try {
            header('Content-Type: application/json; charset=utf-8');
            $request = Typecho_Request::getInstance();
            $actionName = basename($request->getRequestUri());
            $request_body = json_decode(file_get_contents('php://input'), true);

            if ($request_body === null) throw new Exception('无效的请求体 (Invalid JSON)', 400);

            $security = Typecho_Widget::widget('Widget_Security');
            $security->check($request_body['token'] ?? '');

            switch ($actionName) {
                case self::TEST_ACTION_NAME:
                    $testPrompt = ['model' => $request_body['model'], 'messages' => [['role' => 'user', 'content' => 'Hi']], 'max_tokens' => 2];
                    $response = self::doApiRequest($request_body['baseUrl'], $request_body['apiKey'], $testPrompt, 10);
                    if ($response['status'] == 'success') {
                        echo json_encode(['status' => 'success', 'message' => '连接成功！API工作正常。']);
                    } else {
                        throw new Exception($response['error']);
                    }
                    exit;

                case self::PROXY_ACTION_NAME:
                    $options = Helper::options()->plugin('echoAI');
                    $fullPrompt = "你是一位专业作家，请根据以下关键词和要求创作一篇文章：\n\n关键词：" . ($request_body['keywords'] ?? '') . "\n具体要求：" . ($request_body['prompt'] ?: "生成一篇结构完整、语言流畅的文章");
                    $postData = ['model' => $options->model, 'messages' => [['role' => 'user', 'content' => $fullPrompt]], 'temperature' => (float)$options->temperature, 'stream' => false];
                    $response = self::doApiRequest($options->baseUrl, $options->apiKey, $postData);
                    if ($response['status'] == 'success') {
                         echo json_encode($response);
                    } else {
                         throw new Exception($response['error']);
                    }
                    exit;

                default:
                    throw new Exception('未知的 Action 请求 (收到的Action: ' . htmlspecialchars($actionName) . ')', 404);
            }
        } catch (Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code >= 600) { $code = 403; }
            http_response_code($code);
            echo json_encode(['status'  => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }

    private static function doApiRequest($baseUrl, $apiKey, $postData, $timeout = 90)
    {

        $fullApiUrl = rtrim($baseUrl, '/') . '/chat/completions';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) return ['status' => 'error', 'error' => 'cURL Error: ' . $curl_error];

        $responseData = json_decode($response, true);
        if ($http_code >= 400 || (isset($responseData['error']))) {
            $errorMessage = $responseData['error']['message'] ?? $response; 
            return ['status' => 'error', 'error' => "API返回错误 (HTTP {$http_code}): {$errorMessage}"];
        }
        
        if ($timeout < 90 && $http_code < 300) return ['status' => 'success'];
        
        if (!isset($responseData['choices'][0]['message']['content'])) {
             return ['status' => 'error', 'error' => 'API返回了预料外的数据结构。未找到content字段。'];
        }

        return ['status' => 'success', 'content' => $responseData['choices'][0]['message']['content']];
    }
    
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
}
