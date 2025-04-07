require('dotenv').config();
const express = require('express');
const cors = require('cors');
const path = require('path');
const { OpenAI } = require('openai');

const app = express();
const PORT = process.env.PORT || 3000;

// Initialize OpenAI
const openai = new OpenAI({ apiKey: process.env.OPENAI_API_KEY });

// Middleware
app.use(cors({
    origin: process.env.NODE_ENV === 'development' ? 'http://localhost:3000' : 'your-production-url.com'
}));
app.use(express.json({ limit: '50mb' }));
app.use(express.static(path.join(__dirname, 'public')));

// Error handling middleware
app.use((err, req, res, next) => {
    console.error(err.stack);
    res.status(500).json({ error: 'Something went wrong!' });
});

// API endpoint for OpenAI
app.post('/api/generate', async (req, res) => {
    try {
        const { prompt, maxTokens } = req.body;

        const strictPrompt = `${prompt}\n\nIMPORTANT: Return the response exactly as requested in the prompt (e.g., HTML if specified). Follow all formatting instructions precisely.`;

        const completion = await openai.chat.completions.create({
            model: 'gpt-3.5-turbo',
            messages: [
                { role: 'system', content: 'You are a precise content generator that strictly follows user instructions.' },
                { role: 'user', content: strictPrompt }
            ],
            temperature: 0.3, // Lowered for stricter adherence
            max_tokens: maxTokens || 2000
        });

        res.json({ content: completion.choices[0].message.content });
    } catch (error) {
        console.error('OpenAI API error:', error);
        res.status(500).json({ error: 'Error generating content' });
    }
});

// Serve frontend routes
app.get(['/', '/generate'], (req, res) => {
    res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// 404 handler
app.use((req, res) => {
    res.status(404).sendFile(path.join(__dirname, 'public', '404.html'));
});

// Server startup
app.listen(PORT, () => {
    console.log(`Server running on: http://localhost:${PORT}\nEnvironment: ${process.env.NODE_ENV || 'development'}`);
});

// Handle unhandled errors
process.on('unhandledRejection', (err) => console.error('Unhandled rejection:', err));
process.on('uncaughtException', (err) => {
    console.error('Uncaught exception:', err);
    process.exit(1);
});