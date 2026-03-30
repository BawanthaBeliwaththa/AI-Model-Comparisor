# Overview

This Kaggle notebook serves as the backend AI model server for the
Multi-Model Q&A System. It loads multiple language models from Kaggle's
benchmarks and provides streaming API endpoints for answering questions.

## Notebook Information

  **Property**     **Value**
  ---------------- -------------------
  File Name        `benchmark.ipynb`
  Python Version   3.11.15
  Hardware         GPU Enabled
  Internet         Enabled
  Accelerator      GPU

  : Notebook Specifications

# Prerequisites

## Required Dependencies

Install the following packages in the first cell:

``` {.bash language="bash" caption="Installation Command"}
pip install flask flask_cors pyngrok
```

  **Package**    **Purpose**
  -------------- ---------------------------------------
  `flask`        Web framework for API endpoints
  `flask_cors`   Cross-origin resource sharing support
  `pyngrok`      Expose local server via public URL

  : Dependency Description

## Kaggle Benchmarks Module

The notebook requires the `kaggle_benchmarks` module which provides
access to pre-loaded language models:

``` {.python language="python" caption="Import Kaggle Benchmarks"}
import kaggle_benchmarks as kbench

# Get all available models
ALL_MODELS = list(kbench.llms.keys())
print(f"Found {len(ALL_MODELS)} models")
```

# Notebook Structure

## Cell 1: Install Dependencies

First cell installs required packages:

``` {.python language="python" caption="Cell 1 - Dependencies"}
pip install flask flask_cors pyngrok
```

## Cell 2: Main Application Server

The main server code is organized into the following sections:

### Model Loading Section

``` {.python language="python" caption="Loading Models"}
print("="*60)
print("🤖 MULTI-MODEL Q&A PLATFORM - STREAMING")
print("="*60)

print("\n📦 LOADING KAGGLE MODELS...")

try:
    import kaggle_benchmarks as kbench
    print("✅ Kaggle benchmarks module loaded")
    
    # Get all available models
    ALL_MODELS = list(kbench.llms.keys())
    print(f"📋 Found {len(ALL_MODELS)} models")
    
    # Create model objects
    MODELS = {}
    for model_name in ALL_MODELS:
        try:
            MODELS[model_name] = kbench.llms[model_name]
            print(f"   ✅ Loaded: {model_name}")
        except Exception as e:
            print(f"   ⚠️ Could not load {model_name}: {e}")
    
    print(f"\n✅ Loaded {len(MODELS)} models successfully")
    KAGGLE_AVAILABLE = True
    
except ImportError as e:
    print(f"⚠️ Kaggle benchmarks not available: {e}")
    KAGGLE_AVAILABLE = False
    MODELS = {}
```

### Flask Application Setup

``` {.python language="python" caption="Flask Initialization"}
app = Flask(__name__)
CORS(app)

# Store Q&A for research
qa_history = []
```

# API Endpoints

## 1. /ask_stream - Streaming Answers

Streams answers from all models one by one using Server-Sent Events
(SSE).

### Endpoint Details {#endpoint-details .unnumbered}

  **Property**    **Value**
  --------------- -------------------
  Method          POST
  Content-Type    application/json
  Response Type   text/event-stream

### Request Body {#request-body .unnumbered}

``` {.json language="json" caption="Request Format"}
{
    "question": "What is 2+2?",
    "session_id": "session_12345"
}
```

### Response Format {#response-format .unnumbered}

Each response is sent as a Server-Sent Event:

``` {.json language="json" caption="Success Response"}
data: {
    "success": true,
    "model": "google/gemini-2.0-flash",
    "answer": "4",
    "time": 0.85,
    "index": 0,
    "total": 4
}
```

``` {.json language="json" caption="Completion Message"}
data: {
    "complete": true,
    "total": 4
}
```

### Implementation Code {#implementation-code .unnumbered}

``` {.python language="python" caption="/ask_stream Implementation"}
@app.route('/ask_stream', methods=['POST', 'OPTIONS'])
def ask_stream():
    if request.method == 'OPTIONS':
        return '', 200
    
    try:
        data = request.get_json()
        question = data.get('question', '')
        session_id = data.get('session_id', '')
        
        if not question:
            return jsonify({'error': 'No question provided'}), 400
        
        def generate():
            model_list = list(MODELS.keys())
            total_models = len(model_list)
            
            for idx, model_name in enumerate(model_list):
                try:
                    start_time = time.time()
                    model = MODELS[model_name]
                    response = model.prompt(question)
                    generation_time = time.time() - start_time
                    
                    result = {
                        'success': True,
                        'model': model_name,
                        'answer': response,
                        'time': round(generation_time, 2),
                        'index': idx,
                        'total': total_models
                    }
                    
                    qa_history.append({
                        'timestamp': datetime.now().isoformat(),
                        'question': question,
                        'model': model_name,
                        'answer': response,
                        'time': generation_time,
                        'session_id': session_id
                    })
                    
                    yield f"data: {json.dumps(result)}\n\n"
                    
                except Exception as e:
                    result = {
                        'success': False,
                        'model': model_name,
                        'error': str(e),
                        'index': idx,
                        'total': total_models
                    }
                    yield f"data: {json.dumps(result)}\n\n"
                
                time.sleep(0.3)
            
            yield f"data: {json.dumps({'complete': True, 'total': total_models})}\n\n"
        
        return Response(stream_with_context(generate()), 
                        mimetype='text/event-stream',
                        headers={
                            'Cache-Control': 'no-cache',
                            'X-Accel-Buffering': 'no'
                        })
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500
```

## 2. /feedback - Submit Feedback

Stores user feedback for model answers.

### Request Body {#request-body-1 .unnumbered}

``` {.json language="json" caption="Feedback Format"}
{
    "question": "What is 2+2?",
    "model": "google/gemini-2.0-flash",
    "answer": "4",
    "rating": "correct",
    "confidence": 5,
    "note": "Good answer",
    "timestamp": "2026-03-31T10:00:00"
}
```

### Implementation Code {#implementation-code-1 .unnumbered}

``` {.python language="python" caption="/feedback Implementation"}
@app.route('/feedback', methods=['POST', 'OPTIONS'])
def submit_feedback():
    if request.method == 'OPTIONS':
        return '', 200
    
    try:
        data = request.get_json()
        
        feedback_file = __DIR__ + '/feedback.csv'
        
        import os
        file_exists = os.path.exists(feedback_file)
        
        with open(feedback_file, 'a') as f:
            if not file_exists:
                f.write("timestamp,question,model,answer,rating,confidence,note,user_agent\n")
            
            f.write(f"{datetime.now().isoformat()},"
                   f'"{data.get("question","")}",'
                   f'"{data.get("model","")}",'
                   f'"{data.get("answer","")[:1000]}",'
                   f'"{data.get("rating","")}",'
                   f'{data.get("confidence",3)},'
                   f'"{data.get("note","")}",'
                   f'"{request.headers.get("User-Agent","")}"\n')
        
        return jsonify({'success': True})
        
    except Exception as e:
        return jsonify({'error': str(e)}), 500
```

## 3. /models - List Models

Returns all available models with details.

### Response Format {#response-format-1 .unnumbered}

``` {.json language="json" caption="/models Response"}
{
    "models": [
        "google/gemini-2.0-flash",
        "google/gemini-2.5-flash",
        "deepseek-ai/deepseek-v3.1"
    ],
    "count": 3,
    "details": {
        "google/gemini-2.0-flash": {
            "name": "gemini-2.0-flash",
            "provider": "google",
            "full_name": "google/gemini-2.0-flash"
        }
    }
}
```

## 4. /health - Health Check

Returns server status and metrics.

### Response Format {#response-format-2 .unnumbered}

``` {.json language="json" caption="/health Response"}
{
    "status": "healthy",
    "models_loaded": 4,
    "total_queries": 127,
    "timestamp": "2026-03-31T10:00:00"
}
```

## 5. /export - Export Data

Exports all Q&A history as CSV.

### Response {#response .unnumbered}

Returns a CSV file with all recorded questions and answers.

## 6. / - Root Endpoint

Returns API information.

### Response Format {#response-format-3 .unnumbered}

``` {.json language="json" caption="Root Endpoint Response"}
{
    "name": "Multi-Model Q&A Platform",
    "endpoints": {
        "POST /ask_stream": "Stream answers from all models one by one",
        "POST /feedback": "Submit feedback for answers",
        "GET /models": "List all models",
        "GET /export": "Export Q&A history"
    }
}
```

# Server Startup

## Ngrok Configuration

The notebook automatically configures ngrok to expose the server
publicly:

``` {.python language="python" caption="Ngrok Setup"}
try:
    from pyngrok import ngrok
    ngrok.set_auth_token("YOUR_NGROK_AUTH_TOKEN")
    public_url = ngrok.connect(5000)
    print(f"\n🔗 PUBLIC URL: {public_url}")
    print("   Copy this URL for your PHP app!")
except Exception as e:
    print(f"\n⚠️ Ngrok error: {e}")
```

## Running the Server

``` {.python language="python" caption="Start Flask Server"}
print("\n" + "="*60)
print("🤖 MULTI-MODEL Q&A PLATFORM - READY")
print("="*60)
print(f"🤖 Models Loaded: {len(MODELS)}")
print("\n🌐 Endpoint:")
print("   POST /ask_stream - Stream answers from all models")
print("   POST /feedback - Submit feedback")
print("="*60)

app.run(host='0.0.0.0', port=5000, debug=False, threaded=True)
```

# Configuration Guide

## Ngrok Authentication

To use ngrok, you need an authentication token:

1.  Sign up at <https://ngrok.com>

2.  Get your auth token from the dashboard

3.  Replace the token in the code:

``` {.python language="python"}
ngrok.set_auth_token("YOUR_AUTH_TOKEN_HERE")
```

## Environment Variables

Optional environment variables for configuration:

  **Variable**   **Description**
  -------------- ------------------------------------
  `PORT`         Server port (default: 5000)
  `DEBUG`        Enable debug mode (default: False)

# Data Storage

## QA History Storage

All Q&A interactions are stored in memory:

``` {.python language="python"}
qa_history = []  # Stores all questions and answers
```

Each entry contains:

-   `timestamp` - When the question was asked

-   `question` - The user's question

-   `model` - Which model answered

-   `answer` - The model's response

-   `time` - Generation time in seconds

-   `session_id` - User session identifier

## Feedback Storage

User feedback is stored in CSV format:

``` {.bash language="bash" caption="feedback.csv Format"}
timestamp,question,model,answer,rating,confidence,note,user_agent
2026-03-31T10:00:00,"What is 2+2?","google/gemini-2.0-flash","4","correct",5,"Good",Mozilla/5.0...
```

# Troubleshooting

## Common Issues

  **Issue**                                            **Solution**
  ---------------------------------------------------- ------------------------------------------------------------------
  `ImportError: No module named ’kaggle_benchmarks’`   Kaggle benchmarks module is pre-installed in Kaggle environments
  Ngrok connection failed                              Check internet connection and auth token
  Models not loading                                   Verify GPU is enabled and internet is on
  CORS errors                                          CORS is already enabled via `flask_cors`

  : Troubleshooting Guide

## Debug Mode

Enable debug mode for development:

``` {.python language="python"}
app.run(host='0.0.0.0', port=5000, debug=True, threaded=True)
```

## Logging

Server logs are printed to console:

-   Model loading status

-   Question received

-   Model response times

-   Feedback submissions

-   Errors

# Integration with PHP Frontend

## Connection Configuration

In your PHP application, configure the Kaggle URL:

``` {.php language="php" caption="config.php"}
$config_file = 'kaggle_config.json';
$config = json_decode(file_get_contents($config_file), true);
$kaggle_url = $config['kaggle_url'] ?? null;
```

## Testing Connection

Use the test scripts to verify connection:

``` {.bash language="bash"}
# Run test.php to verify connection
http://your-domain.com/test.php
```

# Performance Metrics

## Model Loading Time

\- Each model loads sequentially - Loading time varies by model size -
Total models: 10-20 depending on Kaggle benchmarks

## Response Time

\- Average response: 0.5 - 3 seconds per model - Streaming: 0.3 second
delay between models - Total time for 4 models: 2-12 seconds

# Security Considerations

1.  **Ngrok Token Security**: Do not expose your ngrok auth token

2.  **Public URL**: Ngrok URLs are publicly accessible

3.  **CORS**: CORS is enabled for all origins (restrict in production)

4.  **Data Storage**: Feedback CSV is stored in Kaggle environment

# Export and Backup

## Export Q&A History

Access the export endpoint:

``` {.bash language="bash"}
curl -X GET https://your-ngrok-url.ngrok-free.app/export > qa_export.csv
```

## Download Feedback CSV

Feedback CSV can be downloaded from Kaggle's file browser.

# Support

For issues and questions:

-   Check console output for error messages

-   Verify ngrok is running and URL is accessible

-   Ensure internet is enabled in Kaggle settings

-   Confirm GPU is enabled for faster model loading
