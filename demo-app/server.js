const express = require('express');
const path = require('path');
const fs = require('fs');

const app = express();
const PORT = process.env.PORT || 3000;

app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, 'public')));
app.use('/mobile', express.static(path.join(__dirname, '..', 'mobile-app', 'public')));

// Download routes
app.get('/download/plugin', (req, res) => {
    const zipPath = path.join(__dirname, '..', 'releases', 'cartara-pro-v5.0.0.zip');
    if (fs.existsSync(zipPath)) {
        res.download(zipPath, 'cartara-pro-v5.0.0.zip');
    } else {
        res.status(404).send('فایل افزونه یافت نشد.');
    }
});

app.get('/download/app', (req, res) => {
    const apkPath = path.join(__dirname, '..', 'releases', 'cartara-companion-app-v5.0.0.apk');
    if (fs.existsSync(apkPath)) {
        res.download(apkPath, 'cartara-companion-app-v5.0.0.apk');
    } else {
        res.status(404).send('فایل اپلیکیشن موبایل یافت نشد.');
    }
});

// Alias for generic download button
app.get('/download-plugin', (req, res) => {
    res.redirect('/download/plugin');
});

// API route for SMS simulation
app.post('/api/parse-sms', (req, res) => {
    const { sms } = req.body;
    if (!sms) {
        return res.status(400).json({ error: 'SMS body is empty' });
    }

    const clean = sms.replace(/[,،٬\s]/g, ' ');
    let amount = 0;
    let card = '';
    let ref = '';

    const amtMatch = clean.match(/(?:واریز|مبلغ|انتقال|افزایش)\s*[:=]?\s*([0-9]+)\s*(?:ریال|تومان)?/i) || clean.match(/\+([0-9]{4,12})/);
    if (amtMatch) {
        amount = parseInt(amtMatch[1], 10);
        if (clean.includes('تومان') && amount < 1000000000) {
            amount *= 10;
        }
    }

    const cardMatch = clean.match(/(?:کارت|حساب|به)\s*[:=]?\s*([0-9\*\-]{4,19})/i);
    if (cardMatch) {
        card = cardMatch[1];
    }

    const refMatch = clean.match(/(?:پیگیری|رهگیری|مرجع|ارجاع|کد)\s*[:=]?\s*([0-9a-zA-Z]{5,15})/i);
    if (refMatch) {
        ref = refMatch[1];
    }

    res.json({
        success: true,
        parsed: {
            amount,
            formattedAmount: amount > 0 ? (amount / 10).toLocaleString('fa-IR') + ' تومان (' + amount.toLocaleString('fa-IR') + ' ریال)' : 'تشخیص داده نشد',
            card: card || 'کارت پیش‌فرض',
            ref: ref || '۹۸۷۶۵۴۳۲',
            isMatch: amount > 0
        }
    });
});

app.listen(PORT, '0.0.0.0', () => {
    console.log(`CartAra Pro Full Ecosystem Live on http://0.0.0.0:${PORT}`);
});
