// DOM Elements
const elements = {
    step1: document.getElementById('step1'),
    step2: document.getElementById('step2'),
    step3: document.getElementById('step3'),
    step4: document.getElementById('step4'),
    loadingIndicator: document.getElementById('loadingIndicator'),
    focusKeyword: document.getElementById('focusKeyword'),
    userIntent: document.getElementById('userIntent'),
    suggestSecondaryKeywordsBtn: document.getElementById('suggestSecondaryKeywordsBtn'),
    secondaryKeywords: document.getElementById('secondaryKeywords'),
    generateTitlesBtn: document.getElementById('generateTitlesBtn'),
    titleSuggestions: document.getElementById('titleSuggestions'),
    customTitle: document.getElementById('customTitle'),
    generateOutlineBtn: document.getElementById('generateOutlineBtn'),
    outlineEditor: document.getElementById('outlineEditor'),
    generateArticleBtn: document.getElementById('generateArticleBtn'),
    finalArticle: document.getElementById('finalArticle'),
    backToStep1: document.getElementById('backToStep1'),
    backToStep2: document.getElementById('backToStep2'),
    backToStep3: document.getElementById('backToStep3'),
    newArticleBtn: document.getElementById('newArticleBtn'),
    copyArticleIcon: document.getElementById('copyArticleIcon'),
    tabArticleGenerator: document.getElementById('tabArticleGenerator'),
    tabSeoAnalysis: document.getElementById('tabSeoAnalysis'),
    seoContentEditor: document.getElementById('seoContentEditor'),
    seoTitleInput: document.getElementById('seoTitleInput'),
    targetKeyword: document.getElementById('targetKeyword'),
    suggestKeywordsBtn: document.getElementById('suggestKeywordsBtn'),
    keywordSuggestionsList: document.getElementById('keywordSuggestionsList'),
    analyzeSeoBtn: document.getElementById('analyzeSeoBtn'),
    copySeoBtn: document.getElementById('copySeoBtn'),
    seoChecklist: document.getElementById('seoChecklist'),
    seoScore: document.getElementById('seoScore'),
    seoPreviewTitle: document.getElementById('seoPreviewTitle'),
    seoPreviewUrl: document.getElementById('seoPreviewUrl'),
    seoPreviewMeta: document.getElementById('seoPreviewMeta'),
    editMetaBtn: document.getElementById('editMetaBtn'),
    metaDescriptionInput: document.getElementById('metaDescriptionInput'),
    titleCharCounter: document.getElementById('titleCharCounter'),
    metaCharCounter: document.getElementById('metaCharCounter'),
    keywordDensityOutput: document.getElementById('keywordDensityOutput'),
    readabilityOutput: document.getElementById('readabilityOutput'),
    internalLinksOutput: document.getElementById('internalLinksOutput'),
    toneOutput: document.getElementById('toneOutput'),
    boldBtn: document.getElementById('boldBtn'),
    italicBtn: document.getElementById('italicBtn'),
    linkBtn: document.getElementById('linkBtn'),
    headingsBtn: document.getElementById('headingsBtn'),
    imageUrlBtn: document.getElementById('imageUrlBtn'),
    imageUploadBtn: document.getElementById('imageUploadBtn'),
    imageFileInput: document.getElementById('imageFileInput')
};

// State
let selectedTitle = '';
let currentOutline = '';
let currentMetaDescription = '';
let suggestedKeywords = [];
let secondaryKeywords = [];

document.addEventListener('DOMContentLoaded', () => {
    elements.generateTitlesBtn.addEventListener('click', generateTitles);
    elements.suggestSecondaryKeywordsBtn.addEventListener('click', suggestSecondaryKeywords);
    elements.generateOutlineBtn.addEventListener('click', generateOutline);
    elements.generateArticleBtn.addEventListener('click', generateArticle);
    elements.backToStep1.addEventListener('click', () => changeStep(1));
    elements.backToStep2.addEventListener('click', () => changeStep(2));
    elements.backToStep3.addEventListener('click', () => changeStep(3));
    elements.newArticleBtn.addEventListener('click', resetForm);
    elements.copyArticleIcon.addEventListener('click', copyArticleGeneratorContent);
    elements.tabArticleGenerator.addEventListener('click', (e) => { e.preventDefault(); switchTab('articleGenerator'); });
    elements.tabSeoAnalysis.addEventListener('click', (e) => { e.preventDefault(); switchTab('seoAnalysis'); });
    elements.analyzeSeoBtn.addEventListener('click', analyzeSeoContent);
    elements.copySeoBtn.addEventListener('click', copySeoContent);
    elements.editMetaBtn.addEventListener('click', toggleMetaEdit);
    elements.suggestKeywordsBtn.addEventListener('click', suggestKeywords);
    elements.seoContentEditor.addEventListener('input', updateSeoPreview);
    elements.seoTitleInput.addEventListener('input', updateSeoPreview);
    elements.metaDescriptionInput.addEventListener('input', updateMetaPreview);
    elements.boldBtn.addEventListener('click', () => formatText('bold'));
    elements.italicBtn.addEventListener('click', () => formatText('italic'));
    elements.linkBtn.addEventListener('click', insertLink);
    elements.headingsBtn.nextElementSibling.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            formatBlock(item.getAttribute('data-tag'));
        });
    });
    elements.imageUrlBtn.addEventListener('click', insertImageUrl);
    elements.imageUploadBtn.addEventListener('click', () => elements.imageFileInput.click());
    elements.imageFileInput.addEventListener('change', insertImageFile);
});

function switchTab(featureId) {
    document.querySelectorAll('.feature-section').forEach(section => section.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(tab => tab.classList.remove('active'));
    document.getElementById(featureId).classList.add('active');
    document.getElementById(`tab${featureId.charAt(0).toUpperCase() + featureId.slice(1)}`).classList.add('active');
    if (featureId === 'articleGenerator') changeStep(1);
    if (featureId === 'seoAnalysis') updateSeoPreview();
}

function changeStep(stepNumber) {
    [elements.step1, elements.step2, elements.step3, elements.step4].forEach(step => step.classList.remove('active'));
    elements[`step${stepNumber}`].classList.add('active');
}

function resetForm() {
    elements.focusKeyword.value = '';
    elements.userIntent.value = 'informational';
    elements.secondaryKeywords.innerHTML = '';
    elements.titleSuggestions.innerHTML = '';
    elements.customTitle.value = '';
    elements.outlineEditor.innerHTML = '';
    elements.finalArticle.innerHTML = '';
    selectedTitle = '';
    currentOutline = '';
    secondaryKeywords = [];
    changeStep(1);
}

function showLoading() { elements.loadingIndicator.style.display = 'block'; }
function hideLoading() { elements.loadingIndicator.style.display = 'none'; }

function setButtonLoading(button, isLoading) {
    if (isLoading) {
        button.classList.add('btn-loading');
        button.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...`;
        button.disabled = true;
    } else {
        button.classList.remove('btn-loading');
        button.disabled = false;
        if (button.id === 'generateTitlesBtn') button.innerHTML = 'Generate Titles';
        else if (button.id === 'generateOutlineBtn') button.innerHTML = 'Generate Outline';
        else if (button.id === 'generateArticleBtn') button.innerHTML = 'Generate Article';
        else if (button.id === 'analyzeSeoBtn') button.innerHTML = 'Analyze Content';
        else if (button.id === 'suggestSecondaryKeywordsBtn') button.innerHTML = 'Suggest Secondary Keywords';
        else if (button.id === 'suggestKeywordsBtn') button.innerHTML = 'Suggest Keywords';
    }
}

async function callOpenAI(prompt, maxTokens = 2000) {
    try {
        const response = await fetch('/api/generate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prompt, maxTokens })
        });
        if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
        const data = await response.json();
        return data.content || '';
    } catch (error) {
        console.error('Error in callOpenAI:', error.message);
        alert(`Error: ${error.message}. Please try again or adjust your input.`);
        return '';
    }
}

async function suggestSecondaryKeywords() {
    const button = elements.suggestSecondaryKeywordsBtn;
    setButtonLoading(button, true);
    const focusKeyword = elements.focusKeyword.value.trim();
    const intent = elements.userIntent.value;
    if (!focusKeyword) {
        setButtonLoading(button, false);
        return alert('Please enter a focus keyword first.');
    }
    const prompt = `Generate 5 long-tail SEO keywords related to "${focusKeyword}" for ${intent} intent. Format as a plain list, one per line, no numbering or symbols.`;
    const keywords = await callOpenAI(prompt, 200);
    if (keywords) {
        elements.secondaryKeywords.innerHTML = '<p><strong>Secondary Keywords:</strong></p>';
        secondaryKeywords = [];
        keywords.split('\n').forEach(keyword => {
            if (keyword.trim()) {
                secondaryKeywords.push(keyword.trim());
                const p = document.createElement('p');
                p.textContent = keyword.trim();
                elements.secondaryKeywords.appendChild(p);
            }
        });
    }
    setButtonLoading(button, false);
}

async function generateTitles() {
    const button = elements.generateTitlesBtn;
    setButtonLoading(button, true);
    const focusKeyword = elements.focusKeyword.value.trim();
    const intent = elements.userIntent.value;
    if (!focusKeyword) {
        setButtonLoading(button, false);
        return alert('Please enter a focus keyword.');
    }
    const prompt = `Generate 5 compelling, SEO-optimized article titles for "${focusKeyword}" with ${intent} intent. Format as a numbered list, each under 70 characters.`;
    const titles = await callOpenAI(prompt, 300);
    if (titles) {
        elements.titleSuggestions.innerHTML = '';
        const titleList = document.createElement('div');
        titleList.className = 'list-group';
        titles.split('\n').forEach(title => {
            if (title.trim()) {
                const titleText = title.replace(/^\d+\.\s*/, '').trim();
                const titleOption = document.createElement('button');
                titleOption.className = 'list-group-item list-group-item-action title-option';
                titleOption.textContent = titleText;
                titleOption.addEventListener('click', () => {
                    document.querySelectorAll('.title-option').forEach(opt => opt.classList.remove('selected'));
                    titleOption.classList.add('selected');
                    selectedTitle = titleText;
                    elements.customTitle.value = titleText;
                });
                titleList.appendChild(titleOption);
            }
        });
        elements.titleSuggestions.appendChild(titleList);
        changeStep(2);
    }
    setButtonLoading(button, false);
}

async function generateOutline() {
    const button = elements.generateOutlineBtn;
    setButtonLoading(button, true);
    const title = elements.customTitle.value.trim() || selectedTitle;
    const focusKeyword = elements.focusKeyword.value.trim();
    const intent = elements.userIntent.value;
    if (!title) {
        setButtonLoading(button, false);
        return alert('Please select or enter a title.');
    }
    selectedTitle = title;
    const secondaryList = secondaryKeywords.length ? `Incorporate these secondary keywords: ${secondaryKeywords.join(', ')}.` : '';
    const prompt = `Create a detailed SEO outline for an article titled "${title}" with ${intent} intent and focus keyword "${focusKeyword}". ${secondaryList} Include 1 H1 (the title), Other headings beneath. Under each sub-heading, add at least five points with brief descriptions. The outline should have an Introduction, body and Conclusion. Format as plain text, bold headings with <b> tags (e.g., <b>H2 Heading</b>), indent subheadings with 2 spaces, no markdown symbols (#, *, etc.), clear and concise.`;
    const outline = await callOpenAI(prompt, 1100);
    if (outline) {
        currentOutline = outline;
        renderEditableOutline(outline);
        changeStep(3);
    }
    setButtonLoading(button, false);
}

function renderEditableOutline(outline) {
    elements.outlineEditor.innerHTML = '';
    const lines = outline.split('\n').map(line => line.trim());
    lines.forEach(line => {
        if (line) {
            const input = document.createElement('input');
            input.type = 'text';
            input.className = 'form-control';
            if (line.startsWith('<b>')) {
                input.className += ' fw-bold';
                input.value = line.replace(/<b>|<\/b>/g, '').trim();
            } else {
                input.value = line.trim();
                input.style.marginLeft = '20px';
            }
            elements.outlineEditor.appendChild(input);
        }
    });
}

function getEditedOutline() {
    const inputs = elements.outlineEditor.querySelectorAll('input');
    let outline = '';
    inputs.forEach((input, i) => {
        const isHeading = input.classList.contains('fw-bold');
        const indent = isHeading ? '' : '  ';
        const text = isHeading ? `<b>${input.value}</b>` : input.value;
        outline += `${indent}${text}\n`;
    });
    return outline.trim();
}

async function generateArticle() {
    const button = elements.generateArticleBtn;
    setButtonLoading(button, true);
    const outline = getEditedOutline();
    const intent = elements.userIntent.value;
    if (!outline) {
        setButtonLoading(button, false);
        return alert('Please generate an outline first.');
    }
    const prompt = `Write a detailed SEO-optimized article with ${intent} intent following this outline: ${outline}. It must be atleast 1000 words long.
     The article should be informative, engaging, and well-structured. Use the focus keyword "${elements.focusKeyword.value.trim()}" 8-12 times throughout the article.
     Use HTML tags: <h1> for "${selectedTitle}", <h2> and <h3> for subheadings (remove <b> tags from outline), <p> for paragraphs, <ul> and <li> for lists in at least 2 sections. 
     Each heading must have at least 3 paragraphs.
     Ensure the flesch reading ease score is above 60.
     Use a conversational tone, and avoid jargon.   
     Include a conclusion section summarizing the main points and providing actionable takeaways. 
     Add factual examples, necessary statistics, and relevant data.
     Write in prose and be descriptive.
     Follow the instructions to the latter.
     Format as HTML.`;
    const article = await callOpenAI(prompt, 4000);
    if (article) {
        elements.finalArticle.innerHTML = article;
        const finalWordCount = countWords(article.replace(/<[^>]+>/g, ''));
        console.log(`Generated article word count: ${finalWordCount}`);
        changeStep(4);
    }
    setButtonLoading(button, false);
}

function copyArticleGeneratorContent() {
    const articleContent = elements.finalArticle.innerText;
    if (!articleContent) return alert('No article to copy!');
    navigator.clipboard.writeText(articleContent)
        .then(() => {
            alert('Article copied to clipboard!');
            elements.copyArticleIcon.classList.replace('bi-clipboard', 'bi-clipboard-check');
            setTimeout(() => elements.copyArticleIcon.classList.replace('bi-clipboard-check', 'bi-clipboard'), 2000);
        })
        .catch(err => alert(`Failed to copy: ${err.message}`));
}

// SEO Analysis Functions
async function suggestKeywords() {
    const button = elements.suggestKeywordsBtn;
    setButtonLoading(button, true);
    const keyword = elements.targetKeyword.value.trim();
    if (!keyword) {
        setButtonLoading(button, false);
        return alert('Please enter a target keyword first.');
    }
    const prompt = `Generate 10 related SEO keywords or long-tail variations for "${keyword}". Format as a plain list, one per line.`;
    const suggestions = await callOpenAI(prompt, 300);
    if (suggestions) {
        elements.keywordSuggestionsList.innerHTML = '';
        suggestedKeywords = [];
        suggestions.split('\n').forEach(suggestion => {
            if (suggestion.trim()) {
                const trimmedSuggestion = suggestion.trim();
                suggestedKeywords.push(trimmedSuggestion);
                const item = document.createElement('div');
                item.className = 'list-group-item keyword-suggestion';
                item.textContent = trimmedSuggestion;
                item.addEventListener('click', () => {
                    elements.targetKeyword.value = trimmedSuggestion;
                    analyzeSeoContent();
                });
                elements.keywordSuggestionsList.appendChild(item);
            }
        });
    }
    setButtonLoading(button, false);
}

async function analyzeSeoContent() {
    const button = elements.analyzeSeoBtn;
    setButtonLoading(button, true);
    const title = elements.seoTitleInput.value.trim();
    const content = elements.seoContentEditor.innerHTML.trim();
    const keyword = elements.targetKeyword.value.trim().toLowerCase();
    if (!title || !content || !keyword) {
        setButtonLoading(button, false);
        return alert('Please provide a title, content, and target keyword.');
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(`<div>${content}</div>`, 'text/html');
    const body = doc.querySelector('div');
    const textContent = body.textContent;

    const checklist = [
        { name: 'Target keyword created', weight: 10, check: () => !!keyword },
        { name: 'Target keyword in title', weight: 6, check: () => title.toLowerCase().includes(keyword) },
        { name: 'Target keyword in meta description', weight: 10, check: () => currentMetaDescription.toLowerCase().includes(keyword) },
        { name: 'Target keyword in title URL', weight: 5, check: () => elements.seoPreviewUrl.textContent.toLowerCase().includes(keyword) },
        { name: 'Target keyword in first two paragraphs', weight: 5, check: () => {
            const paragraphs = body.querySelectorAll('p');
            return Array.from(paragraphs).slice(0, 2).some(p => p.textContent.toLowerCase().includes(keyword));
        }},
        { name: 'Target keyword used at least 4x in content', weight: 5, check: () => {
            const matches = (textContent.toLowerCase().match(new RegExp(keyword, 'g')) || []).length;
            return matches >= 4;
        }},
        { name: 'Content length at least 600 words', weight: 5, check: () => countWords(textContent) >= 600 },
        { name: 'Target keyword in subheadings', weight: 5, check: () => {
            const headings = body.querySelectorAll('h1, h2, h3, h4, h5, h6');
            return Array.from(headings).some(h => h.textContent.toLowerCase().includes(keyword));
        }},
        { name: 'At least 1 media content used', weight: 5, check: () => body.querySelectorAll('img, video').length >= 1 },
        { name: 'At least 1 media ALT with target keyword', weight: 5, check: () => {
            const images = body.querySelectorAll('img');
            return Array.from(images).some(img => img.getAttribute('alt')?.toLowerCase().includes(keyword));
        }},
        { name: 'Links to at least 1 external source', weight: 5, check: () => {
            const links = body.querySelectorAll('a');
            return Array.from(links).some(a => a.href && !a.href.includes('example.com'));
        }},
        { name: 'At least 1 internal link', weight: 10, check: () => {
            const links = body.querySelectorAll('a');
            return Array.from(links).some(a => a.href && a.href.includes('example.com'));
        }},
        { name: 'At least 1 do-follow link', weight: 5, check: () => {
            const links = body.querySelectorAll('a');
            return Array.from(links).some(a => !a.hasAttribute('rel') || a.getAttribute('rel') !== 'nofollow');
        }},
        { name: 'At least 1 list', weight: 4, check: () => body.querySelectorAll('ul, ol').length >= 1 },
        { name: 'Short paragraphs (max 120 words)', weight: 15, check: () => {
            const paragraphs = body.querySelectorAll('p');
            return Array.from(paragraphs).every(p => countWords(p.textContent) <= 120);
        }}
    ];

    let totalScore = 0;
    elements.seoChecklist.innerHTML = '';
    checklist.forEach(item => {
        const passed = item.check();
        totalScore += passed ? item.weight : 0;
        const li = document.createElement('li');
        li.innerHTML = `<span class="dot ${passed ? 'green' : 'red'}"></span> ${item.name}`;
        elements.seoChecklist.appendChild(li);
    });
    elements.seoScore.textContent = `SEO Score: ${totalScore}%`;
    elements.seoScore.className = `score-box ${totalScore < 50 ? 'red' : totalScore < 70 ? 'yellow' : 'green'}`;

    updateSeoPreview();

    const totalWords = countWords(textContent);
    const keywordCount = (textContent.toLowerCase().match(new RegExp(keyword, 'g')) || []).length;
    const density = totalWords > 0 ? (keywordCount / totalWords * 100).toFixed(2) : 0;
    let densitySuggestion = density < 1 ? 'Increase keyword usage.' : density > 2 ? 'Reduce keyword usage.' : 'Density is optimal.';
    const missingKeywords = suggestedKeywords.filter(k => !textContent.toLowerCase().includes(k.toLowerCase()));
    const keywordCoverage = missingKeywords.length > 0 ? `Missing related terms: ${missingKeywords.slice(0, 3).join(', ')}.` : 'Good coverage of related terms.';
    elements.keywordDensityOutput.innerHTML = `
        <p><strong>Target Keyword:</strong> "${keyword}"</p>
        <p><strong>Occurrences:</strong> ${keywordCount}</p>
        <p><strong>Density:</strong> ${density}%</p>
        <p class="suggestion"><strong>Density Suggestion:</strong> ${densitySuggestion}</p>
        <p class="suggestion"><strong>Keyword Coverage:</strong> ${keywordCoverage}</p>
    `;

    const sentences = textContent.split(/[.!?]+/).filter(s => s.trim()).length;
    const syllables = countSyllables(textContent);
    const fleschScore = totalWords && sentences ? (206.835 - 1.015 * (totalWords / sentences) - 84.6 * (syllables / totalWords)).toFixed(1) : 0;
    const avgSentenceLength = totalWords / sentences || 0;
    const passiveSentences = estimatePassiveVoice(textContent);
    let readabilitySuggestion = fleschScore < 60 ? 'Simplify text.' : avgSentenceLength > 20 ? 'Shorten sentences.' : passiveSentences > 10 ? 'Reduce passive voice.' : 'Readability is great!';
    const h1Count = body.querySelectorAll('h1').length;
    const h2Count = body.querySelectorAll('h2').length;
    const structureStatus = h1Count === 1 ? '1 H1 (good)' : h1Count > 1 ? 'Too many H1s' : 'No H1 found';
    const structureSuggestion = h2Count < 3 ? 'Add more H2s (3-6 ideal).' : 'Heading structure looks good.';
    const wordCount = totalWords;
    const performanceScore = wordCount < 1000 ? 'Aim for 1000+ words.' : body.querySelectorAll('img, video').length > 5 ? 'Optimize media usage.' : 'Performance is solid.';
    elements.readabilityOutput.innerHTML = `
        <p><strong>Flesch Score:</strong> ${fleschScore} (60-70 ideal)</p>
        <p><strong>Avg Sentence Length:</strong> ${avgSentenceLength.toFixed(1)} words</p>
        <p><strong>Passive Voice:</strong> ${passiveSentences}%</p>
        <p class="suggestion"><strong>Readability:</strong> ${readabilitySuggestion}</ertos>
        <p><strong>Structure:</strong> ${structureStatus}, ${h2Count} H2s</p>
        <p class="suggestion"><strong>Structure Suggestion:</strong> ${structureSuggestion}</p>
        <p><strong>Word Count:</strong> ${wordCount}</p>
        <p class="suggestion"><strong>Performance:</strong> ${performanceScore}</p>
    `;

    const internalLinks = Array.from(body.querySelectorAll('a')).filter(a => a.href.includes('example.com'));
    const externalLinks = Array.from(body.querySelectorAll('a')).filter(a => a.href && !a.href.includes('example.com'));
    const internalSuggestion = internalLinks.length === 0 ? 'Add 1-2 internal links.' : 'Internal links look good.';
    const externalSuggestion = externalLinks.length === 0 ? 'Add 1-3 external links.' : externalLinks.length > 3 ? 'Reduce external links.' : 'External links are balanced.';
    elements.internalLinksOutput.innerHTML = `
        <p><strong>Internal Links:</strong> ${internalLinks.length}</p>
        <p class="suggestion"><strong>Suggestion:</strong> ${internalSuggestion}</p>
        <p><strong>External Links:</strong> ${externalLinks.length}</p>
        <p class="suggestion"><strong>Suggestion:</strong> ${externalSuggestion}</p>
        <p><strong>Internal Link Ideas:</strong></p>
        <p class="suggestion">${extractKeyPhrases(textContent, keyword).slice(0, 3).map(p => `Link "${p.text}" to /${p.text.replace(/\s+/g, '-')}/`).join('<br>') || 'No phrases found.'}</p>
    `;

    const tone = analyzeTone(textContent);
    elements.toneOutput.innerHTML = `
        <p><strong>Tone:</strong> ${tone.label}</p>
        <p class="suggestion"><strong>Suggestion:</strong> ${tone.suggestion}</p>
    `;

    setButtonLoading(button, false);
}

function copySeoContent() {
    const title = elements.seoTitleInput.value.trim();
    const content = elements.seoContentEditor.innerText;
    const fullContent = title ? `${title}\n\n${content}` : content;
    if (!fullContent) return alert('No content to copy!');
    navigator.clipboard.writeText(fullContent)
        .then(() => alert('Content copied to clipboard!'))
        .catch(err => alert(`Failed to copy: ${err.message}`));
}

function toggleMetaEdit() {
    if (elements.metaDescriptionInput.classList.contains('d-none')) {
        elements.metaDescriptionInput.classList.remove('d-none');
        elements.editMetaBtn.textContent = 'Save Meta';
        elements.metaDescriptionInput.value = currentMetaDescription || '';
    } else {
        currentMetaDescription = elements.metaDescriptionInput.value.slice(0, 150);
        elements.metaDescriptionInput.classList.add('d-none');
        elements.editMetaBtn.textContent = 'Edit Meta Description';
        updateMetaPreview();
    }
}

function updateSeoPreview() {
    const title = elements.seoTitleInput.value.trim();
    const titleLength = title.length;
    elements.seoPreviewTitle.textContent = titleLength > 60 ? title.slice(0, 57) + '...' : title || 'Article Title';
    elements.titleCharCounter.textContent = `Title: ${titleLength}/60 characters`;
    elements.titleCharCounter.className = `char-counter ${titleLength > 60 ? 'red' : ''}`;
    elements.seoPreviewMeta.textContent = currentMetaDescription || 'Enter a meta description...';
}

function updateMetaPreview() {
    currentMetaDescription = elements.metaDescriptionInput.value.slice(0, 150);
    const metaLength = currentMetaDescription.length;
    elements.seoPreviewMeta.textContent = metaLength > 150 ? currentMetaDescription.slice(0, 147) + '...' : currentMetaDescription;
    elements.metaCharCounter.textContent = `Meta: ${metaLength}/150 characters`;
    elements.metaCharCounter.className = `char-counter ${metaLength > 150 ? 'red' : ''}`;
}

function formatText(command) {
    document.execCommand(command, false, null);
    elements.seoContentEditor.focus();
}

function insertLink() {
    const url = prompt('Enter the URL:');
    if (url) {
        document.execCommand('createLink', false, url);
        elements.seoContentEditor.focus();
    }
}

function formatBlock(tag) {
    const selection = window.getSelection();
    if (!selection.rangeCount) return;

    const range = selection.getRangeAt(0);
    let selectedNode = range.commonAncestorContainer;
    if (selectedNode.nodeType === 3) selectedNode = selectedNode.parentElement;
    const blockElement = selectedNode.closest('p, h1, h2, h3, h4, h5, h6');

    if (blockElement && blockElement.tagName.toLowerCase() !== tag) {
        const newElement = document.createElement(tag);
        newElement.innerHTML = blockElement.innerHTML || '<br>';
        blockElement.parentNode.replaceChild(newElement, blockElement);
        const newRange = document.createRange();
        newRange.selectNodeContents(newElement);
        if (newElement.childNodes.length) {
            newRange.setStart(newElement.firstChild, 0);
            newRange.setEnd(newElement.firstChild, 0);
        }
        selection.removeAllRanges();
        selection.addRange(newRange);
    } else if (!blockElement) {
        document.execCommand('formatBlock', false, tag);
    }
    elements.seoContentEditor.focus();
}

function insertImageUrl() {
    const url = prompt('Enter the image URL:');
    if (url) {
        document.execCommand('insertImage', false, url);
        const img = elements.seoContentEditor.querySelector(`img[src="${url}"]`);
        if (img && !img.getAttribute('alt')) img.setAttribute('alt', elements.targetKeyword.value || 'Image');
        elements.seoContentEditor.focus();
    }
}

function insertImageFile(event) {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = (e) => {
            document.execCommand('insertImage', false, e.target.result);
            const img = elements.seoContentEditor.querySelector(`img[src="${e.target.result}"]`);
            if (img && !img.getAttribute('alt')) img.setAttribute('alt', elements.targetKeyword.value || 'Image');
            elements.seoContentEditor.focus();
        };
        reader.readAsDataURL(file);
    }
    elements.imageFileInput.value = '';
}

function countWords(text) { return text.split(/\s+/).filter(word => word.length > 0).length; }
function countSyllables(text) {
    return text.split(/\s+/).reduce((total, word) => {
        word = word.toLowerCase().replace(/[^a-z]/g, '');
        if (!word) return total;
        let syllables = 0;
        let vowels = 'aeiouy';
        let prevCharWasVowel = false;
        for (let char of word) {
            let isVowel = vowels.includes(char);
            if (isVowel && !prevCharWasVowel) syllables++;
            prevCharWasVowel = isVowel;
        }
        if (word.endsWith('e')) syllables--;
        return total + Math.max(1, syllables);
    }, 0);
}

function estimatePassiveVoice(text) {
    const passiveIndicators = /\b(is|are|was|were|be|been|being)\s+\w+ed\b/gi;
    const matches = (text.match(passiveIndicators) || []).length;
    const sentences = text.split(/[.!?]+/).filter(s => s.trim()).length;
    return sentences > 0 ? ((matches / sentences) * 100).toFixed(1) : 0;
}

function extractKeyPhrases(text, keyword) {
    const words = text.toLowerCase().split(/\s+/).filter(w => w.length > 2 && w !== keyword);
    const phraseMap = new Map();
    let currentPhrase = '';
    text.split(/\s+/).forEach((word, i, arr) => {
        if (word.length > 2 && !['a', 'an', 'the', 'and', 'or', 'but'].includes(word.toLowerCase())) {
            currentPhrase += (currentPhrase ? ' ' : '') + word;
            if (currentPhrase.split(' ').length >= 2 && currentPhrase.split(' ').length <= 3) {
                const count = (text.toLowerCase().match(new RegExp(currentPhrase, 'g')) || []).length;
                if (count > 1) phraseMap.set(currentPhrase, count);
            }
            if (currentPhrase.split(' ').length > 3 || i === arr.length - 1 || !arr[i + 1]) currentPhrase = '';
        } else {
            currentPhrase = '';
        }
    });
    return Array.from(phraseMap.entries()).map(([text, count]) => ({ text, count })).sort((a, b) => b.count - a.count);
}

function analyzeTone(text) {
    const positiveWords = ['great', 'good', 'amazing', 'best', 'easy', 'success'];
    const negativeWords = ['problem', 'bad', 'difficult', 'fail', 'worst'];
    let positiveCount = 0, negativeCount = 0;
    text.toLowerCase().split(/\s+/).forEach(word => {
        if (positiveWords.includes(word)) positiveCount++;
        if (negativeWords.includes(word)) negativeCount++;
    });
    if (positiveCount > negativeCount + 2) return { label: 'Positive', suggestion: 'Matches motivational content.' };
    if (negativeCount > positiveCount + 2) return { label: 'Negative', suggestion: 'Consider a more neutral tone.' };
    return { label: 'Neutral', suggestion: 'Good for informational content.' };
}