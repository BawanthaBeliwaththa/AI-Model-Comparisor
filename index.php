<?php
session_start();

if (!file_exists('data')) {
    mkdir('data', 0777, true);
}

$ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];
$time = date('Y-m-d H:i:s');

$log_entry = "$time|$ip|$user_agent\n";
file_put_contents('data/visitors.log', $log_entry, FILE_APPEND);

$active_file = 'data/active_users.json';
$active = [];
if (file_exists($active_file)) {
    $active = json_decode(file_get_contents($active_file), true) ?: [];
}
$active[$ip] = [
    'last_seen' => time(),
    'user_agent' => $user_agent,
    'first_seen' => $active[$ip]['first_seen'] ?? time()
];
$active = array_filter($active, function($u) {
    return (time() - $u['last_seen']) < 300;
});
file_put_contents($active_file, json_encode($active));

$sample_questions = [
    'Who is Bawantha Beliwaththa ?',
    'Explain quantum computing in simple terms',
    'What is 4 × 4?',
    'If A is for apple what is for B ?',
    'What is machine learning?'
];

$models = [];
$kaggle_url = null;
$config_file = 'kaggle_config.json';
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true);
    $kaggle_url = $config['kaggle_url'] ?? null;
}

if ($kaggle_url) {
    $ch = curl_init($kaggle_url . '/models');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['ngrok-skip-browser-warning: true']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($data && isset($data['models'])) {
            $models = $data['models'];
        }
    }
}

if (empty($models)) {
    $models = [
        'google/gemini-2.0-flash',
        'google/gemini-2.5-flash',
        'deepseek-ai/deepseek-v3.1',
        'qwen/qwen3-235b-a22b-instruct-2507'
    ];
}

$total_models = count($models);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Model Q&A - Compare AI Models</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1600px; margin: 0 auto; }
        
        .header {
            text-align: center;
            padding: 40px 0 30px;
            animation: fadeInDown 0.8s ease-out;
        }
        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, #a0a0ff 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }
        .header p { color: rgba(255,255,255,0.8); font-size: 1.1rem; }
        .stats-badge {
            display: inline-flex;
            align-items: center;
            gap: 20px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            padding: 8px 20px;
            margin-top: 15px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        .stats-badge span { color: white; font-size: 0.9rem; }
        
        .question-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(255,255,255,0.1);
            animation: fadeInUp 0.8s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .question-card h5 { color: white; font-weight: 600; margin-bottom: 20px; }
        .question-input {
            width: 100%;
            padding: 16px 20px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            color: white;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            resize: vertical;
        }
        .question-input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(255,255,255,0.15);
        }
        .question-input::placeholder { color: rgba(255,255,255,0.5); }
        .suggestions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .suggestion-badge {
            background: rgba(255,255,255,0.1);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
            color: rgba(255,255,255,0.8);
        }
        .suggestion-badge:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            transform: translateY(-2px);
            color: white;
        }
        .ask-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 20px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }
        .ask-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(102,126,234,0.4);
        }
        .ask-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        
        .models-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        .model-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
            transition: all 0.3s;
            animation: fadeInUp 0.6s ease-out;
        }
        .model-card:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.08);
            border-color: rgba(102,126,234,0.5);
        }
        .model-header {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .model-icon { font-size: 1.8rem; }
        .model-info { flex: 1; }
        .model-name { font-size: 1rem; font-weight: 700; color: white; margin: 0; }
        .model-provider { font-size: 0.7rem; opacity: 0.7; color: rgba(255,255,255,0.7); }
        .response-area { padding: 15px; }
        .response-text {
            background: rgba(0,0,0,0.3);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.8rem;
            line-height: 1.5;
            min-height: 100px;
            max-height: 150px;
            overflow-y: auto;
            margin-bottom: 12px;
            color: rgba(255,255,255,0.9);
            white-space: pre-wrap;
        }
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .rating-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .rating-btn {
            flex: 1;
            padding: 8px;
            border: 1px solid rgba(255,255,255,0.2);
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255,255,255,0.7);
        }
        .rating-btn.correct:hover { background: rgba(16,185,129,0.2); border-color: #10b981; }
        .rating-btn.correct.selected { background: #10b981; color: white; }
        .rating-btn.incorrect:hover { background: rgba(239,68,68,0.2); border-color: #ef4444; }
        .rating-btn.incorrect.selected { background: #ef4444; color: white; }
        
        .confidence-slider { margin: 10px 0; }
        .confidence-slider label { font-size: 0.7rem; color: rgba(255,255,255,0.6); }
        .confidence-slider input {
            width: 100%;
            height: 4px;
            border-radius: 5px;
            background: rgba(255,255,255,0.2);
        }
        .confidence-value { font-size: 0.65rem; color: rgba(255,255,255,0.5); margin-top: 5px; }
        .save-note {
            width: 100%;
            padding: 8px 12px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            font-size: 0.7rem;
            color: white;
            margin-top: 8px;
        }
        .data-panel {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-top: 40px;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .progress { height: 8px; border-radius: 10px; background: rgba(255,255,255,0.1); }
        .progress-bar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            transition: width 0.3s ease;
            height: 100%;
            width: 0%;
        }
        .footer-note {
            text-align: center;
            padding: 30px 0 20px;
            font-size: 0.8rem;
            color: rgba(255,255,255,0.5);
        }
        .admin-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 10px 15px;
            border-radius: 50px;
            color: white;
            text-decoration: none;
            font-size: 0.8rem;
            z-index: 100;
        }
        @media (max-width: 768px) {
            .models-grid { grid-template-columns: 1fr; }
            .header h1 { font-size: 1.8rem; }
            .stats-badge { flex-direction: column; gap: 5px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-robot"></i> Multi-Model Q&A</h1>
            <p>Compare <?php echo $total_models; ?> AI models side by side</p>
            <div class="stats-badge">
                <span><i class="fas fa-microchip"></i> <?php echo $total_models; ?> Models</span>
                <span><i class="fas fa-chart-line"></i> Real-time Streaming</span>
            </div>
        </div>
        
        <div class="question-card">
            <h5><i class="fas fa-question-circle"></i> Ask Any Question</h5>
            <textarea id="userQuestion" class="question-input" rows="3" placeholder="Type your question here... e.g., 'What is the capital of Sri Lanka?'"></textarea>
            <div class="suggestions">
                <?php foreach ($sample_questions as $q): ?>
                <span class="suggestion-badge" onclick="setQuestion('<?php echo addslashes($q); ?>')"><?php echo htmlspecialchars(substr($q, 0, 40)) . (strlen($q) > 40 ? '...' : ''); ?></span>
                <?php endforeach; ?>
            </div>
            <button class="ask-btn" id="askBtn" onclick="askAllModels()">
                <i class="fas fa-paper-plane"></i> Ask All <?php echo $total_models; ?> Models
            </button>
        </div>
        
        <div id="modelsGrid" class="models-grid"></div>
        
        <div class="data-panel">
            <div class="mt-3" style="margin-top: 15px;">
                <div class="progress"><div id="progressBar" class="progress-bar"></div></div>
                <p class="small mt-2" id="statsText" style="color: rgba(255,255,255,0.5); font-size: 0.7rem;">Rate responses to build your dataset</p>
            </div>
        </div>
        
        <div class="footer-note">
            <small>Responses are recorded anonymously. Rate each response to help improve AI benchmarks!</small>
        </div>
    </div>
    
    <a href="admin_dashboard.php" class="admin-link"><i class="fas fa-chart-line"></i> Admin</a>
    
    <script>
        const MODELS = <?php echo json_encode($models); ?>;
        let currentQuestion = '';
        let responses = {};
        let ratings = {};
        let confidences = {};
        let notes = {};
        let totalModels = MODELS.length;
        let receivedCount = 0;
        let sessionId = localStorage.getItem('session_id') || 'session_' + Date.now();
        localStorage.setItem('session_id', sessionId);
        
        function getProviderIcon(provider) {
            const icons = {
                'google': '<i class="fab fa-google"></i>',
                'anthropic': '<i class="fas fa-brain"></i>',
                'deepseek-ai': '<i class="fas fa-chart-line"></i>',
                'qwen': '<i class="fas fa-fire"></i>',
                'zai': '<i class="fas fa-gem"></i>'
            };
            return icons[provider] || '<i class="fas fa-microchip"></i>';
        }
        
        function renderModels() {
            const container = document.getElementById('modelsGrid');
            if (!container) return;
            
            container.innerHTML = '';
            
            MODELS.forEach(modelKey => {
                const provider = modelKey.split('/')[0] || 'unknown';
                const name = modelKey.split('/')[1] || modelKey;
                const icon = getProviderIcon(provider);
                const safeKey = modelKey.replace(/[\/\.]/g, '_');
                
                const card = document.createElement('div');
                card.className = 'model-card';
                card.id = `model-${safeKey}`;
                card.innerHTML = `
                    <div class="model-header">
                        <div class="model-icon">${icon}</div>
                        <div class="model-info">
                            <div class="model-name">${escapeHtml(name)}</div>
                            <div class="model-provider">${escapeHtml(provider)}</div>
                        </div>
                    </div>
                    <div class="response-area">
                        <div class="response-text" id="response-${safeKey}">
                            <span class="loading-spinner"></span> Waiting for question...
                        </div>
                        <div class="rating-buttons">
                            <button class="rating-btn correct" id="correct-${safeKey}" onclick="rateResponse('${modelKey}', 'correct')" disabled>
                                <i class="fas fa-check-circle"></i> Correct
                            </button>
                            <button class="rating-btn incorrect" id="incorrect-${safeKey}" onclick="rateResponse('${modelKey}', 'incorrect')" disabled>
                                <i class="fas fa-times-circle"></i> Incorrect
                            </button>
                        </div>
                        <div class="confidence-slider">
                            <label>Your confidence:</label>
                            <input type="range" id="confidence-${safeKey}" min="1" max="5" value="3" onchange="updateConfidence('${modelKey}', this.value)" disabled>
                            <div class="confidence-value" id="conf-value-${safeKey}">Confidence: 3/5</div>
                        </div>
                        <textarea class="save-note" id="note-${safeKey}" placeholder="Add notes (optional)..." rows="1" disabled></textarea>
                    </div>
                `;
                container.appendChild(card);
            });
        }
        
        async function askAllModels() {
            const question = document.getElementById('userQuestion').value.trim();
            if (!question) {
                alert('Please enter a question first!');
                return;
            }
            
            currentQuestion = question;
            receivedCount = 0;
            responses = {};
            ratings = {};
            confidences = {};
            notes = {};
            
            const askBtn = document.getElementById('askBtn');
            askBtn.disabled = true;
            askBtn.innerHTML = '<span class="loading-spinner"></span> Asking ' + totalModels + ' models...';
            
            MODELS.forEach(modelKey => {
                const safeKey = modelKey.replace(/[\/\.]/g, '_');
                const responseDiv = document.getElementById(`response-${safeKey}`);
                if (responseDiv) {
                    responseDiv.innerHTML = '<span class="loading-spinner"></span> Thinking...';
                }
                const correctBtn = document.getElementById(`correct-${safeKey}`);
                const incorrectBtn = document.getElementById(`incorrect-${safeKey}`);
                const confidenceSlider = document.getElementById(`confidence-${safeKey}`);
                const noteField = document.getElementById(`note-${safeKey}`);
                
                if (correctBtn) { correctBtn.disabled = true; correctBtn.classList.remove('selected'); }
                if (incorrectBtn) { incorrectBtn.disabled = true; incorrectBtn.classList.remove('selected'); }
                if (confidenceSlider) confidenceSlider.disabled = true;
                if (noteField) noteField.disabled = true;
            });
            
            updateProgress();
            
            try {
                const response = await fetch('sim.php?action=ask_stream', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question: question, session_id: sessionId })
                });
                
                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';
                
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;
                    
                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n\n');
                    buffer = lines.pop();
                    
                    for (const line of lines) {
                        if (line.startsWith('data: ')) {
                            const data = JSON.parse(line.slice(6));
                            
                            if (data.complete) {
                                askBtn.disabled = false;
                                askBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Ask All ' + totalModels + ' Models';
                                break;
                            }
                            
                            if (data.model) {
                                updateModelCard(data.model, data);
                                receivedCount++;
                                updateProgress();
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Stream error:', error);
                alert('Connection error. Please try again.');
                askBtn.disabled = false;
                askBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Ask All ' + totalModels + ' Models';
            }
        }
        
        function updateModelCard(modelKey, data) {
            const safeKey = modelKey.replace(/[\/\.]/g, '_');
            const responseDiv = document.getElementById(`response-${safeKey}`);
            const correctBtn = document.getElementById(`correct-${safeKey}`);
            const incorrectBtn = document.getElementById(`incorrect-${safeKey}`);
            const confidenceSlider = document.getElementById(`confidence-${safeKey}`);
            const noteField = document.getElementById(`note-${safeKey}`);
            
            if (responseDiv) {
                if (data.success) {
                    let formattedResponse = data.answer.replace(/\n/g, '<br>');
                    responseDiv.innerHTML = formattedResponse;
                } else {
                    responseDiv.innerHTML = `<span style="color: #ef4444;">⚠️ Error: ${escapeHtml(data.error)}</span>`;
                }
            }
            
            if (correctBtn) correctBtn.disabled = false;
            if (incorrectBtn) incorrectBtn.disabled = false;
            if (confidenceSlider) confidenceSlider.disabled = false;
            if (noteField) noteField.disabled = false;
            
            responses[modelKey] = data.success ? data.answer : data.error;
            if (ratings[modelKey]) saveResponse(modelKey);
        }
        
        function rateResponse(modelKey, correctness) {
            const safeKey = modelKey.replace(/[\/\.]/g, '_');
            const correctBtn = document.getElementById(`correct-${safeKey}`);
            const incorrectBtn = document.getElementById(`incorrect-${safeKey}`);
            
            if (correctness === 'correct') {
                correctBtn.classList.add('selected');
                incorrectBtn.classList.remove('selected');
                ratings[modelKey] = 'correct';
            } else {
                incorrectBtn.classList.add('selected');
                correctBtn.classList.remove('selected');
                ratings[modelKey] = 'incorrect';
            }
            saveResponse(modelKey);
            updateProgress();
        }
        
        function updateConfidence(modelKey, value) {
            const safeKey = modelKey.replace(/[\/\.]/g, '_');
            const confValue = document.getElementById(`conf-value-${safeKey}`);
            if (confValue) confValue.innerHTML = `Confidence: ${value}/5`;
            confidences[modelKey] = parseInt(value);
            if (ratings[modelKey]) saveResponse(modelKey);
        }
        
        async function saveResponse(modelKey) {
            if (!currentQuestion || !responses[modelKey] || !ratings[modelKey]) return;
            
            const safeKey = modelKey.replace(/[\/\.]/g, '_');
            const confidence = confidences[modelKey] || 3;
            const note = document.getElementById(`note-${safeKey}`)?.value || '';
            
            try {
                await fetch('sim.php?action=feedback', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        question: currentQuestion,
                        model: modelKey,
                        answer: responses[modelKey],
                        rating: ratings[modelKey],
                        confidence: confidence,
                        note: note,
                        session_id: sessionId,
                        timestamp: new Date().toISOString()
                    })
                });
            } catch (error) {
                console.error('Save failed:', error);
            }
        }
        
        function updateProgress() {
            const rated = Object.keys(ratings).length;
            const percent = totalModels > 0 ? (rated / totalModels) * 100 : 0;
            const progressBar = document.getElementById('progressBar');
            if (progressBar) progressBar.style.width = `${percent}%`;
            
            const statsText = document.getElementById('statsText');
            if (statsText) {
                statsText.innerHTML = `📊 Rated: ${rated}/${totalModels} responses | Received: ${receivedCount}/${totalModels}`;
            }
        }
        
        function setQuestion(question) {
            document.getElementById('userQuestion').value = question;
            document.getElementById('userQuestion').focus();
        }
        
        function exportData() {
            window.location.href = 'sim.php?action=export';
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        document.addEventListener('DOMContentLoaded', renderModels);
    </script>
</body>
</html>
