<?php
session_start();
require_once 'config.php';
require_once 'api_handler.php';

$apiHandler = new APIHandler();

// Chat geçmişini session'da sakla
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// API endpoint'leri
$LLM_API_URL = 'https://backend.buildpicoapps.com/aero/run/llm-api?pk=v1-Z0FBQUFBQm5HUEtMSjJkakVjcF9IQ0M0VFhRQ0FmSnNDSHNYTlJSblE0UXo1Q3RBcjFPcl9YYy1OZUhteDZWekxHdWRLM1M1alNZTkJMWEhNOWd4S1NPSDBTWC12M0U2UGc9PQ==';
$IMAGE_API_URL = 'https://backend.buildpicoapps.com/aero/run/image-generation-api?pk=v1-Z0FBQUFBQm5HUEtMSjJkakVjcF9IQ0M0VFhRQ0FmSnNDSHNYTlJSblE0UXo1Q3RBcjFPcl9YYy1OZUhteDZWekxHdWRLM1M1alNZTkJMWEhNOWd4S1NPSDBTWC12M0U2UGc9PQ==';

// AJAX isteği işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'send_message') {
        $message = $apiHandler->sanitizeMessage($_POST['message'] ?? '');
        
        if ($message === false) {
            echo json_encode(['success' => false, 'message' => ERROR_MESSAGES['invalid_request']]);
            exit;
        }
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Mesaj boş olamaz.']);
            exit;
        }
        
        // Rate limiting kontrolü
        if (!$apiHandler->checkRateLimit()) {
            echo json_encode(['success' => false, 'message' => 'Çok fazla istek gönderdiniz. Lütfen 1 dakika bekleyin.']);
            exit;
        }
        
        // Kullanıcı mesajını geçmişe ekle
        $apiHandler->addToChatHistory($message, true);
        
        // API'ye mesaj gönder
        $result = $apiHandler->sendMessage($message);
        
        if ($result['success']) {
            // Bot yanıtını geçmişe ekle
            $apiHandler->addToChatHistory($result['message'], false);
        }
        
        echo json_encode($result);
        exit;
    }
    
    if ($_POST['action'] === 'clear_chat') {
        $apiHandler->clearChatHistory();
        echo json_encode(['success' => true, 'message' => SUCCESS_MESSAGES['chat_cleared']]);
        exit;
    }
    
    if ($_POST['action'] === 'get_history') {
        $history = $apiHandler->getChatHistory();
        echo json_encode(['success' => true, 'history' => $history]);
        exit;
    }
    
    if ($_POST['action'] === 'get_all_chats') {
        $allChats = $apiHandler->getAllChats();
        $activeChat = $apiHandler->getChatHistory();
        echo json_encode(['success' => true, 'allChats' => $allChats, 'activeChat' => $activeChat]);
        exit;
    }
    
    if ($_POST['action'] === 'delete_chat') {
        $idx = intval($_POST['chat_index'] ?? -1);
        $success = $apiHandler->deleteChat($idx);
        echo json_encode(['success' => $success]);
        exit;
    }
}

// Chat geçmişini al
$chatHistory = $apiHandler->getChatHistory();
$allChats = $apiHandler->getAllChats();
$activeChat = $chatHistory;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - <?php echo APP_DESCRIPTION; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background: #181a20;
            overflow: hidden;
        }
        /* Hareketli gradient arka plan */
        .animated-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: -1;
            background: linear-gradient(270deg, #0f2027, #2c5364, #00c6ff, #0072ff, #0f2027);
            background-size: 200% 200%;
            animation: gradientMove 12s ease-in-out infinite;
        }
        @keyframes gradientMove {
            0% {background-position: 0% 50%;}
            50% {background-position: 100% 50%;}
            100% {background-position: 0% 50%;}
        }
        /* Discord benzeri sidebar */
        .discord-sidebar {
            background: rgba(24,26,32,0.95);
            border-right: 2px solid #23272a;
            box-shadow: 2px 0 16px 0 #000a;
        }
        .sidebar-icon {
            transition: all 0.2s;
        }
        .sidebar-icon:hover {
            background: #5865f2;
            color: #fff;
            transform: scale(1.1);
        }
        /* Chat balonları */
        .bubble-user {
            background: linear-gradient(135deg, #5865f2 60%, #00c6ff 100%);
            color: #fff;
        }
        .bubble-bot {
            background: #23272a;
              color: #a7f0e4;
          }
        /* Scrollbar */
        ::-webkit-scrollbar {width: 8px;}
        ::-webkit-scrollbar-thumb {background: #23272a; border-radius: 4px;}
        /* Yazıyor efekti için animasyonlar */
        .animate-bounce {
            animation: bounce 1s infinite alternate;
        }
        .delay-150 { animation-delay: 0.15s; }
        .delay-300 { animation-delay: 0.3s; }
        @keyframes bounce {
            0% { transform: translateY(0); }
            100% { transform: translateY(-8px); }
        }
        @keyframes fade-in { from { opacity: 0; transform: translateX(60px);} to { opacity: 1; transform: translateX(0);} }
        .animate-fade-in { animation: fade-in 0.4s cubic-bezier(.4,0,.2,1); }
    </style>
</head>
<body class="min-h-screen">
    <div class="animated-bg"></div>
    <div class="flex h-screen">
        <!-- Discord Sidebar -->
        <aside class="discord-sidebar w-20 flex flex-col items-center py-6 space-y-4">
            <div class="flex flex-col items-center space-y-4">
                <div class="sidebar-icon w-14 h-14 flex items-center justify-center rounded-2xl bg-[#23272a] text-[#00c6ff] text-3xl font-bold shadow-lg cursor-pointer border-4 border-[#00c6ff]">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="sidebar-icon w-12 h-12 flex items-center justify-center rounded-xl bg-[#23272a] text-[#00c6ff] text-xl cursor-pointer">
                    <i class="fas fa-terminal"></i>
                </div>
                <div class="sidebar-icon w-12 h-12 flex items-center justify-center rounded-xl bg-[#23272a] text-[#43b581] text-xl cursor-pointer">
                    <i class="fas fa-image"></i>
                </div>
                <!-- Chat temizle butonu -->
                <button id="clearChatSidebar" title="Sohbeti Temizle" class="sidebar-icon w-12 h-12 flex items-center justify-center rounded-xl bg-[#23272a] text-[#ff4757] text-xl cursor-pointer mt-6">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="mt-auto flex flex-col items-center space-y-2">
                <div class="sidebar-icon w-10 h-10 flex items-center justify-center rounded-lg bg-[#23272a] text-[#ff4757] text-lg cursor-pointer">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
            </div>
        </aside>
        <!-- Main Chat Area -->
        <main class="flex-1 flex flex-col bg-black/30">
            <!-- Sohbet Başlıkları -->
            <div class="flex items-center gap-2 px-6 pt-4 pb-2 overflow-x-auto" id="chatTitlesBar">
                <?php foreach ($allChats as $i => $chat): ?>
                    <div class="flex items-center gap-1">
                        <button class="chat-title-btn px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-200 <?php echo ($i === count($allChats)) ? 'bg-[#5865f2] text-white' : 'bg-[#23272a] text-[#00c6ff] hover:bg-[#5865f2] hover:text-white'; ?>" onclick="showChat(<?php echo $i; ?>)"><?php echo htmlspecialchars($chat['title']); ?></button>
                        <button class="delete-chat-btn bg-[#23272a] rounded-full p-1 text-gray-400 hover:text-red-500 hover:bg-[#23272a]/80 text-base transition-colors duration-200" onclick="deleteChat(<?php echo $i; ?>)" title="Sohbeti Sil"><i class="fas fa-times"></i></button>
                    </div>
                <?php endforeach; ?>
                <button class="chat-title-btn px-4 py-2 rounded-lg font-semibold text-sm bg-gradient-to-r from-[#00c6ff] to-[#5865f2] text-white ml-2" onclick="showActiveChat()">Aktif Sohbet</button>
                <button id="newChatBtn" class="ml-4 px-4 py-2 rounded-lg font-bold text-white bg-gradient-to-r from-green-400 to-green-600 shadow hover:scale-105 transition-all duration-200 flex items-center gap-2"><i class="fas fa-plus"></i> Yeni Chat</button>
            </div>
            <header class="p-4 border-b border-white/10 flex justify-between items-center glass-effect">
                <h2 class="text-xl font-bold text-white flex items-center gap-2"><i class="fas fa-robot text-[#00c6ff]"></i> <?php echo APP_NAME; ?></h2>
                 <p class="text-green-400 text-sm flex items-center animate-pulse">
                    <span class="w-2.5 h-2.5 bg-green-400 rounded-full mr-2"></span>
                    Bağlantı Şifreli
                 </p>
            </header>
            <div id="chatMessages" class="flex-grow overflow-y-auto p-6 space-y-6">
                 <div class="message-bubble flex items-start space-x-4">
                    <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 bg-[#23272a] border-2 border-[#00c6ff]">
                        <i class="fas fa-robot text-[#00c6ff] text-xl"></i>
                    </div>
                    <div class="bubble-bot rounded-lg px-5 py-3 shadow-lg">
                        <p>Sistem çevrimiçi. Komut bekleniyor...</p>
                    </div>
                </div>
                <!-- PHP ile chat geçmişi -->
                <?php foreach ($chatHistory as $msg): ?>
                    <div class="message-bubble flex items-start space-x-4 <?php echo $msg['type'] === 'user' ? 'justify-end flex-row-reverse' : ''; ?>">
                        <div class="w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 <?php echo $msg['type'] === 'user' ? 'bg-[#5865f2] text-white' : 'bg-[#23272a] text-[#5865f2]'; ?>">
                            <i class="<?php echo $msg['type'] === 'user' ? 'fas fa-user' : 'fab fa-discord'; ?> text-xl"></i>
                        </div>
                        <div class="<?php echo $msg['type'] === 'user' ? 'bubble-user' : 'bubble-bot'; ?> rounded-lg px-5 py-3 shadow-lg max-w-xl">
                            <p><?php echo $msg['message']; ?></p>
                            <span class="block text-xs text-gray-400 mt-1 text-right"><?php echo $msg['timestamp']; ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="p-6 flex-shrink-0">
                 <form id="chatForm" class="flex space-x-4 items-center">
                    <div class="flex-1 relative">
                        <input 
                            type="text" 
                            id="messageInput" 
                            class="w-full rounded-lg border-none bg-[#23272a] text-white px-5 py-3 focus:ring-2 focus:ring-[#5865f2] transition-all duration-200 placeholder-gray-400 shadow-lg"
                            placeholder="Mesajınızı yazın... (örn: /image bir uzay manzarası)"
                            autocomplete="off"
                        >
                    </div>
                    <button type="submit" class="px-6 py-3 rounded-lg bg-[#5865f2] text-white font-bold shadow-lg hover:bg-[#4752c4] transition-all duration-200">
                        Gönder <i class="fas fa-paper-plane ml-2"></i>
                    </button>
                    <button type="button" id="clearChat" class="px-4 py-3 rounded-lg bg-[#23272a] text-gray-300 hover:bg-[#181a20] transition-all duration-200">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </main>
    </div>
    <!-- Kod Paneli (Ultra Modern, Gradientli, Kart Tasarım, Çalıştır Butonlu) -->
    <div id="codePanel" class="fixed top-0 right-0 h-full w-full max-w-2xl flex items-center justify-end z-50" style="display:none;">
        <div class="relative m-4 w-full max-w-2xl rounded-3xl shadow-2xl bg-gradient-to-br from-[#23272a] to-[#181a20] border border-[#23272a] flex flex-col overflow-hidden animate-fade-in" style="box-shadow: 0 8px 48px 0 #00c6ff33, 0 1.5px 8px 0 #23272a99;">
            <div class="flex items-center justify-between p-6 border-b border-[#23272a] bg-gradient-to-r from-[#23272a] to-[#181a20] rounded-t-3xl">
                <div class="flex items-center gap-4">
                    <!-- Dil animasyonlu ikon -->
                    <span id="codePanelAnimIcon" class="rounded-full p-4 text-4xl shadow-xl transition-all duration-300"><i class="fas fa-brain"></i></span>
                    <span id="codePanelTitle" class="text-2xl font-bold text-white tracking-wide">Kod Yanıtı</span>
                </div>
                <button onclick="closeCodePanel()" title="Kapat" class="bg-[#23272a] rounded-full p-3 text-gray-400 hover:text-red-400 hover:bg-[#23272a]/80 text-3xl font-bold transition-colors duration-200"><i class="fas fa-times"></i></button>
            </div>
            <div class="flex-1 overflow-auto p-0 bg-[#181a20] relative flex flex-col">
                <!-- Kod yazılıyor animasyonu -->
                <div id="codePanelLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-[#181a20] bg-opacity-90 z-10" style="display:none;">
                    <div class="flex flex-col items-center gap-4">
                        <span class="text-5xl text-[#00c6ff]"><i class="fas fa-spinner fa-spin"></i></span>
                        <span class="text-[#00c6ff] font-semibold text-xl">Kod yazılıyor...</span>
                        <div class="flex gap-1 mt-2">
                            <span class="w-2 h-2 rounded-full bg-[#00c6ff] animate-bounce"></span>
                            <span class="w-2 h-2 rounded-full bg-[#00c6ff] animate-bounce delay-150"></span>
                            <span class="w-2 h-2 rounded-full bg-[#00c6ff] animate-bounce delay-300"></span>
                        </div>
                    </div>
                </div>
                <!-- Kod bloğu üstü bar -->
                <div class="flex items-center justify-between px-6 pt-6 pb-2">
                    <span id="codePanelLang" class="text-xs px-3 py-1 rounded-full bg-gradient-to-r from-[#00c6ff] to-[#5865f2] text-white font-mono border border-[#00c6ff] shadow-md">Dil</span>
                    <div class="flex gap-2">
                        <button id="runCodeBtn" title="Kodu Çalıştır" class="bg-gradient-to-r from-green-400 to-green-600 text-white px-4 py-2 rounded-full font-bold shadow-lg hover:scale-105 transition-all duration-200 flex items-center gap-2"><i class="fas fa-play"></i> Çalıştır</button>
                        <button id="copyCodeBtn" title="Kodu Kopyala" class="bg-[#23272a] rounded-full p-2 text-gray-400 hover:text-[#00c6ff] hover:bg-[#23272a]/80 text-xl transition-colors duration-200 flex items-center gap-2"><i class="fas fa-copy"></i><span class="hidden sm:inline">Kopyala</span></button>
                    </div>
                </div>
                <div class="flex-1 px-6 pb-6">
                    <pre class="rounded-2xl bg-gradient-to-br from-[#181a20] to-[#23272a] border border-[#23272a] p-6 overflow-auto relative text-base leading-relaxed font-mono text-[#e6e6e6] shadow-inner" style="min-height: 320px;"><code id="codePanelCode" class="language-javascript"></code></pre>
                </div>
            </div>
            <div id="copyToast" class="fixed bottom-8 right-8 bg-[#23272a] text-[#00c6ff] px-4 py-2 rounded-lg shadow-lg text-sm font-semibold opacity-0 pointer-events-none transition-opacity duration-300 z-50 flex items-center gap-2"><i class="fas fa-check-circle"></i> Kopyalandı!</div>
        </div>
    </div>
    <!-- Codepen Modal (Sade, Sabit, Scroll Destekli, Modern) -->
    <div id="codepenModal" class="fixed inset-0 z-[999] flex items-center justify-center bg-black/80 transition-all duration-300" style="display:none;">
        <div class="relative w-full max-w-3xl rounded-2xl bg-[#181a20] shadow-2xl border border-[#23272a] p-0 overflow-hidden animate-fade-in flex flex-col" style="min-height: 500px; max-height: 90vh;">
            <div class="flex items-center justify-between p-5 border-b border-[#23272a] bg-[#23272a] rounded-t-2xl">
                <div class="flex items-center gap-4">
                    <span id="codepenAnimIcon" class="rounded-full p-3 text-3xl shadow-xl bg-[#23272a] text-[#00c6ff]"><i class="fas fa-code"></i></span>
                    <span id="codepenTitle" class="text-xl font-bold text-white tracking-wide">Canlı Kod Testi</span>
                </div>
                <button onclick="closeCodepenModal()" title="Kapat" class="bg-[#23272a] rounded-full p-3 text-gray-400 hover:text-red-400 hover:bg-[#23272a]/80 text-2xl font-bold transition-colors duration-200"><i class="fas fa-times"></i></button>
            </div>
            <!-- Yükleniyor animasyonu -->
            <div id="codepenLoading" class="flex flex-col items-center justify-center flex-1 bg-[#181a20]" style="min-height:300px;">
                <span class="text-5xl text-[#00c6ff] animate-spin"><i class="fas fa-spinner"></i></span>
                <span class="text-white font-semibold text-lg mt-4">Canlı ortam hazırlanıyor...</span>
            </div>
            <div id="codepenContent" class="flex-1 bg-[#181a20] p-0 flex flex-col overflow-y-auto" style="display:none; max-height: 60vh;">
                <!-- Canlı önizleme veya simülasyon burada -->
            </div>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/languages/python.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.7.0/languages/javascript.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const chatMessages = document.getElementById('chatMessages');
        const clearChatSidebar = document.getElementById('clearChatSidebar');

        // Mesajı ekrana ekle
        function addMessage(message, isUser = false, isImage = false) {
            const wrapper = document.createElement('div');
            // Kullanıcı ve bot mesajlarını solda hizala, avatar solda olsun
            wrapper.className = 'message-bubble flex items-start space-x-4 mb-2';
            const iconDiv = document.createElement('div');
            if (isUser) {
                iconDiv.className = 'w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 bg-gradient-to-br from-[#5865f2] to-[#00c6ff] text-white border-2 border-[#5865f2] shadow-lg';
                iconDiv.innerHTML = '<i class="fas fa-user text-xl"></i>';
            } else {
                iconDiv.className = 'w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 bg-[#23272a] text-[#00c6ff] border-2 border-[#00c6ff] shadow-lg';
                iconDiv.innerHTML = '<i class="fas fa-robot text-xl"></i>';
            }
            const msgDiv = document.createElement('div');
            msgDiv.className = (isUser ? 'bubble-user' : 'bubble-bot') + ' rounded-lg px-5 py-3 shadow-lg max-w-xl';
            if (isImage && !isUser) {
                if (message.startsWith('data:image')) {
                    const img = document.createElement('img');
                    img.src = message;
                    img.alt = 'Oluşturulan resim';
                    img.className = 'max-w-xs rounded-lg shadow-lg';
                    msgDiv.appendChild(img);
                } else {
                    msgDiv.innerHTML = '<p>Resim oluşturulamadı veya içerik boş.</p>';
                }
            } else {
                msgDiv.innerHTML = `<p>${message}</p>`;
            }
            wrapper.appendChild(iconDiv);
            wrapper.appendChild(msgDiv);
            chatMessages.appendChild(wrapper);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Yazıyor efekti ekle
        function showTyping() {
            if (document.getElementById('typing-indicator')) return;
            const wrapper = document.createElement('div');
            wrapper.id = 'typing-indicator';
            wrapper.className = 'message-bubble flex items-start space-x-4';
            const iconDiv = document.createElement('div');
            iconDiv.className = 'w-11 h-11 rounded-full flex items-center justify-center flex-shrink-0 bg-[#23272a] text-[#00c6ff]';
            iconDiv.innerHTML = '<i class="fas fa-robot text-xl"></i>';
            const msgDiv = document.createElement('div');
            msgDiv.className = 'bubble-bot rounded-lg px-5 py-3 shadow-lg max-w-xl flex items-center';
            msgDiv.innerHTML = `<span class="typing-dot bg-[#5865f2] w-2 h-2 rounded-full inline-block mr-1 animate-bounce"></span>
                <span class="typing-dot bg-[#5865f2] w-2 h-2 rounded-full inline-block mr-1 animate-bounce delay-150"></span>
                <span class="typing-dot bg-[#5865f2] w-2 h-2 rounded-full inline-block animate-bounce delay-300"></span>
                <span class="ml-2 text-xs text-gray-400">Yapay zeka yazıyor...</span>`;
            wrapper.appendChild(iconDiv);
            wrapper.appendChild(msgDiv);
            chatMessages.appendChild(wrapper);
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        function hideTyping() {
            const typing = document.getElementById('typing-indicator');
            if (typing) typing.remove();
        }

        // AJAX ile mesaj gönder
        async function sendMessage(message) {
            addMessage(message, true);
            messageInput.value = '';
            showTyping();
            // Kod isteği mi kontrolü (örn: /code, /python, /js, /javascript, /kod, /python kodu, vs.)
            const kodIstek = /\b(kod|code|python|javascript|js|c#|c\+\+|java|php|html|css|sql)\b/i.test(message);
            if (kodIstek) {
                openCodePanel('', 'python', 'Yapay Zeka Kod Yanıtı', true); // loading ile paneli aç
            }
            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('message', message);
                const response = await fetch('', { method: 'POST', body: formData });
                const data = await response.json();
                hideTyping();
                if (data.success) {
                    // Kod yanıtı kontrolü (``` veya <code> içeriyorsa)
                    let codeMatch = data.message.match(/```(\w+)?\n([\s\S]*?)```/);
                    if (!codeMatch) {
                        // HTML <code> etiketi ile gelirse
                        codeMatch = data.message.match(/<code.*?>([\s\S]*?)<\/code>/);
                    }
                    if (codeMatch) {
                        let lang = codeMatch[1] || 'python';
                        let code = codeMatch[2] || codeMatch[1] || '';
                        openCodePanel(code, lang, 'Yapay Zeka Kod Yanıtı', false); // loading kapalı
                        // Kod paneli dışında kalan açıklamayı da ekrana yaz
                        let textPart = data.message.replace(/```[\s\S]*?```/, '').replace(/<code[\s\S]*?<\/code>/, '');
                        if (textPart.trim()) addMessage(textPart, false);
                    } else {
                        // Eğer image ise ve base64 veri varsa görsel olarak göster
                        if (data.isImage) {
                            addMessage(data.message, false, true);
                        } else {
                            addMessage(data.message, false);
                        }
                        // Kod panelini kapat (kod gelmediyse)
                        if (kodIstek) closeCodePanel();
                    }
                } else {
                    addMessage(data.message || 'Bir hata oluştu.', false);
                    if (kodIstek) closeCodePanel();
                }
            } catch (err) {
                hideTyping();
                addMessage('Bağlantı hatası.', false);
                if (kodIstek) closeCodePanel();
            }
        }

        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const message = messageInput.value.trim();
            if (!message) return;
            sendMessage(message);
        });
        
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                chatForm.dispatchEvent(new Event('submit', { cancelable: true }));
            }
        });

        // Sidebar'daki chat temizle butonu
        if (clearChatSidebar) {
            clearChatSidebar.addEventListener('click', function() {
                if (confirm('Sohbet geçmişi temizlensin mi?')) {
                    fetch('', {
                        method: 'POST',
                        body: new URLSearchParams({ action: 'clear_chat' })
                    }).then(() => {
                        chatMessages.innerHTML = '';
                    });
                }
            });
        }
    });
    // Kod paneli animasyonlu ikonları
    const langIcons = {
        python: '<i class="fab fa-python animate-bounce"></i>',
        javascript: '<i class="fab fa-js-square animate-spin-slow"></i>',
        html: '<i class="fab fa-html5 animate-pulse"></i>',
        css: '<i class="fab fa-css3-alt animate-pulse"></i>',
        php: '<i class="fab fa-php animate-bounce"></i>',
        java: '<i class="fab fa-java animate-bounce"></i>',
        csharp: '<i class="fas fa-code animate-bounce"></i>',
        default: '<i class="fas fa-brain animate-pulse"></i>'
    };
    function setCodePanelAnimIcon(lang) {
        const iconDiv = document.getElementById('codePanelAnimIcon');
        lang = lang.toLowerCase();
        if (lang.includes('python')) iconDiv.innerHTML = langIcons.python;
        else if (lang.includes('js')) iconDiv.innerHTML = langIcons.javascript;
        else if (lang.includes('html')) iconDiv.innerHTML = langIcons.html;
        else if (lang.includes('css')) iconDiv.innerHTML = langIcons.css;
        else if (lang.includes('php')) iconDiv.innerHTML = langIcons.php;
        else if (lang.includes('java')) iconDiv.innerHTML = langIcons.java;
        else if (lang.includes('c#') || lang.includes('csharp')) iconDiv.innerHTML = langIcons.csharp;
        else iconDiv.innerHTML = langIcons.default;
    }
    // Kod panelini açarken animasyon ikonunu da ayarla
    function openCodePanel(code, lang = 'python', title = 'Kod', loading = false) {
        document.getElementById('codePanel').style.display = 'flex';
        document.getElementById('codePanel').classList.remove('translate-x-full');
        document.getElementById('codePanel').classList.add('translate-x-0');
        document.getElementById('codePanelLang').textContent = lang.toUpperCase();
        document.getElementById('codePanelTitle').textContent = title;
        setCodePanelAnimIcon(lang);
        const codeBlock = document.getElementById('codePanelCode');
        codeBlock.textContent = code || '';
        codeBlock.className = 'language-' + lang;
        window.hljs && hljs.highlightElement(codeBlock);
        document.getElementById('codePanelLoading').style.display = loading ? 'flex' : 'none';
        codeBlock.parentElement.style.opacity = loading ? '0.3' : '1';
    }
    function closeCodePanel() {
        document.getElementById('codePanel').classList.add('translate-x-full');
        setTimeout(() => {
            document.getElementById('codePanel').style.display = 'none';
        }, 300);
    }
    // Çalıştır butonu fonksiyonu
    window.addEventListener('DOMContentLoaded', function() {
        const copyBtn = document.getElementById('copyCodeBtn');
        if (copyBtn) {
            copyBtn.onclick = function() {
                const code = document.getElementById('codePanelCode').textContent;
                navigator.clipboard.writeText(code).then(() => {
                    const toast = document.getElementById('copyToast');
                    toast.style.opacity = '1';
                    setTimeout(() => { toast.style.opacity = '0'; }, 1500);
                });
            };
        }
        const runBtn = document.getElementById('runCodeBtn');
        if (runBtn) {
            runBtn.onclick = function() {
                const code = document.getElementById('codePanelCode').textContent;
                const lang = document.getElementById('codePanelLang').textContent.toLowerCase();
                openCodepenModal(code, lang);
            };
        }
    });
    // Codepen Modal fonksiyonları
    function openCodepenModal(code, lang) {
        document.getElementById('codepenModal').style.display = 'flex';
        setCodepenAnimIcon(lang);
        document.getElementById('codepenTitle').textContent = 'Canlı Kod Testi (' + lang.toUpperCase() + ')';
        // Yükleniyor animasyonu göster
        document.getElementById('codepenLoading').style.display = 'flex';
        document.getElementById('codepenContent').style.display = 'none';
        setTimeout(() => {
            const content = document.getElementById('codepenContent');
            if (["html", "css", "javascript", "js"].includes(lang)) {
                // Canlı önizleme
                let html = '';
                if (lang === 'html') html = code;
                else if (lang === 'css') html = `<style>${code}</style>`;
                else if (lang === 'javascript' || lang === 'js') html = `<script>${code}<\/script>`;
                else html = code;
                content.innerHTML = `<iframe class='w-full h-[400px] rounded-xl bg-white shadow-lg border border-[#23272a] mt-4' style='background:#fff;' sandbox='allow-scripts allow-same-origin' srcdoc="${html.replace(/"/g, '&quot;')}"></iframe>`;
            } else if (lang === 'python') {
                content.innerHTML = `<div class='flex flex-col items-center justify-center h-[300px] text-[#00c6ff] gap-4'><i class='fab fa-python text-5xl animate-bounce'></i><div class='text-base font-bold text-white'>Python kodları tarayıcıda çalıştırılamaz.<br>Bu kodu <a href='https://replit.com/languages/python3' target='_blank' class='underline text-green-400'>Replit</a> veya <a href='https://trinket.io/' target='_blank' class='underline text-green-400'>Trinket</a> gibi bir ortamda deneyebilirsiniz.</div></div>`;
            } else {
                content.innerHTML = `<div class='flex flex-col items-center justify-center h-[300px] text-[#00c6ff] gap-4'><i class='fas fa-code text-5xl animate-bounce'></i><div class='text-base font-bold text-white'>Bu dilde kodu tarayıcıda çalıştırmak mümkün değil.<br>Kodu uygun bir IDE veya online editörde deneyebilirsiniz.</div></div>`;
            }
            document.getElementById('codepenLoading').style.display = 'none';
            content.style.display = 'flex';
        }, 900); // 0.9 saniye animasyon
    }
    function closeCodepenModal() {
        document.getElementById('codepenModal').style.display = 'none';
    }
    function setCodepenAnimIcon(lang) {
        const iconDiv = document.getElementById('codepenAnimIcon');
        lang = lang.toLowerCase();
        if (lang.includes('python')) iconDiv.innerHTML = '<i class="fab fa-python animate-bounce"></i>';
        else if (lang.includes('js')) iconDiv.innerHTML = '<i class="fab fa-js-square animate-spin-slow"></i>';
        else if (lang.includes('html')) iconDiv.innerHTML = '<i class="fab fa-html5 animate-pulse"></i>';
        else if (lang.includes('css')) iconDiv.innerHTML = '<i class="fab fa-css3-alt animate-pulse"></i>';
        else if (lang.includes('php')) iconDiv.innerHTML = '<i class="fab fa-php animate-bounce"></i>';
        else if (lang.includes('java')) iconDiv.innerHTML = '<i class="fab fa-java animate-bounce"></i>';
        else if (lang.includes('c#') || lang.includes('csharp')) iconDiv.innerHTML = '<i class="fas fa-code animate-bounce"></i>';
        else iconDiv.innerHTML = '<i class="fas fa-code animate-pulse"></i>';
    }
    // Sohbet başlıklarına tıklayınca ilgili sohbeti göster
    let currentChatType = 'active'; // 'active' veya 'readonly'
    function renderChat(messages, readonly = false) {
        const chatMessages = document.getElementById('chatMessages');
        chatMessages.innerHTML = '';
        if (!messages || messages.length === 0) {
            chatMessages.innerHTML = `<div class='text-gray-400 text-center mt-10'>Bu sohbette hiç mesaj yok.</div>`;
        } else {
            messages.forEach(msg => {
                addMessage(msg.message, msg.type === 'user');
            });
        }
        // Mesaj kutusu ve gönder butonunu readonly ise devre dışı bırak
        document.getElementById('messageInput').disabled = readonly;
        document.querySelector('#chatForm button[type="submit"]').disabled = readonly;
        if (readonly) {
            document.getElementById('messageInput').placeholder = 'Eski sohbetler salt okunur. Yeni mesaj için Aktif Sohbeti seçin.';
        } else {
            document.getElementById('messageInput').placeholder = 'Mesajınızı yazın... (örn: /image bir uzay manzarası)';
        }
        currentChatType = readonly ? 'readonly' : 'active';
    }
    function showChat(idx) {
        if (allChats[idx]) {
            renderChat(allChats[idx].messages, true);
            highlightChatTitle(idx);
        }
    }
    function showActiveChat() {
        renderChat(activeChat, false);
        highlightChatTitle('active');
    }
    function highlightChatTitle(idx) {
        document.querySelectorAll('.chat-title-btn').forEach((btn, i) => {
            btn.classList.remove('bg-[#5865f2]', 'text-white', 'bg-gradient-to-r', 'from-[#00c6ff]', 'to-[#5865f2]');
            btn.classList.add('bg-[#23272a]', 'text-[#00c6ff]');
            if (idx === 'active' && btn.textContent === 'Aktif Sohbet') {
                btn.classList.remove('bg-[#23272a]', 'text-[#00c6ff]');
                btn.classList.add('bg-gradient-to-r', 'from-[#00c6ff]', 'to-[#5865f2]', 'text-white');
            } else if (typeof idx === 'number' && i === idx) {
                btn.classList.remove('bg-[#23272a]', 'text-[#00c6ff]');
                btn.classList.add('bg-[#5865f2]', 'text-white');
            }
        });
    }
    // Sayfa yüklenince aktif sohbeti göster
    refreshChatsAndShowActive();
    let allChats = <?php echo json_encode($allChats); ?>;
    let activeChat = <?php echo json_encode($activeChat); ?>;
    async function refreshChatsAndShowActive() {
        // AJAX ile başlıkları ve aktif sohbeti çek
        const res = await fetch('', { method: 'POST', body: new URLSearchParams({ action: 'get_all_chats' }) });
        const data = await res.json();
        if (data.success) {
            // Başlıkları güncelle
            const chatTitlesDiv = document.querySelector('.flex.items-center.gap-2.px-6.pt-4.pb-2.overflow-x-auto');
            chatTitlesDiv.innerHTML = '';
            allChats = data.allChats;
            data.allChats.forEach((chat, i) => {
                const btn = document.createElement('button');
                btn.className = 'chat-title-btn px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-200 bg-[#23272a] text-[#00c6ff] hover:bg-[#5865f2] hover:text-white';
                btn.textContent = chat.title;
                btn.onclick = () => showChat(i);
                chatTitlesDiv.appendChild(btn);
            });
            // Aktif Sohbet butonu
            const activeBtn = document.createElement('button');
            activeBtn.className = 'chat-title-btn px-4 py-2 rounded-lg font-semibold text-sm bg-gradient-to-r from-[#00c6ff] to-[#5865f2] text-white ml-2';
            activeBtn.textContent = 'Aktif Sohbet';
            activeBtn.onclick = () => renderChat(data.activeChat, false);
            chatTitlesDiv.appendChild(activeBtn);
            // Yeni Chat butonu
            const newBtn = document.createElement('button');
            newBtn.id = 'newChatBtn';
            newBtn.className = 'ml-4 px-4 py-2 rounded-lg font-bold text-white bg-gradient-to-r from-green-400 to-green-600 shadow hover:scale-105 transition-all duration-200 flex items-center gap-2';
            newBtn.innerHTML = '<i class="fas fa-plus"></i> Yeni Chat';
            newBtn.onclick = newChatHandler;
            chatTitlesDiv.appendChild(newBtn);
            // Mesajları ve aktifChat değişkenini güncelle
            activeChat = data.activeChat;
            renderChat(activeChat, false);
            highlightChatTitle('active');
        }
    }
    function showChatDynamic(idx, allChatsArr) {
        if (allChatsArr[idx]) {
            renderChat(allChatsArr[idx].messages);
        }
    }
    async function newChatHandler() {
        // Yeni chat açınca aktif sohbeti tamamen boşalt
        await fetch('', { method: 'POST', body: new URLSearchParams({ action: 'clear_chat' }) });
        refreshChatsAndShowActive();
    }
    async function deleteChat(idx) {
        if (confirm('Bu sohbeti silmek istediğinize emin misiniz?')) {
            await fetch('', { method: 'POST', body: new URLSearchParams({ action: 'delete_chat', chat_index: idx }) });
            refreshChatsAndShowActive();
        }
    }
    document.getElementById('newChatBtn').onclick = newChatHandler;
    </script>
</body>
</html> 