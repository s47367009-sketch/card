/**
 * CartAra Pro SMS Parser & Receiver Service
 * Parsers Iranian bank formats: Melli, Mellat, Saman, Pasargad, BluBank, Tejarat, Saderat, Sepah, Keshavarzi, Resalat, etc.
 */

export class SmsReceiverService {
    static parseBankSms(text, sender = '') {
        const clean = text.replace(/[,،٬\sـ\t]/g, ' ');
        const result = {
            bank: this.detectBank(text, sender),
            amount: 0,
            formattedAmount: '۰ تومان',
            cardMask: '',
            trackingCode: '',
            balance: '',
            dateTime: new Date().toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit' }),
            rawText: text,
            isDeposit: false
        };

        // Check if deposit
        if (text.includes('واریز') || text.includes('واریزبه') || text.includes('انتقال') || text.includes('پایا') || text.includes('ساتنا') || text.includes('افزایش')) {
            result.isDeposit = true;
        }

        // Amount matching
        const amtMatch = clean.match(/(?:واریز|مبلغ|انتقال|افزایش)\s*[:=]?\s*([0-9]+)\s*(?:ریال|تومان|IRR)?/i) || clean.match(/\+([0-9]{4,12})/);
        if (amtMatch) {
            let val = parseInt(amtMatch[1], 10);
            if (clean.includes('تومان') && val < 1000000000) {
                result.amount = val * 10; // normalize to Rials
                result.formattedAmount = (val).toLocaleString('fa-IR') + ' تومان';
            } else {
                result.amount = val;
                result.formattedAmount = (val / 10).toLocaleString('fa-IR') + ' تومان';
            }
        }

        // Card mask matching
        const cardMatch = clean.match(/(?:کارت|حساب|به)\s*[:=]?\s*([0-9\*\-]{4,19})/i);
        if (cardMatch) {
            result.cardMask = cardMatch[1];
        }

        // Tracking / Reference Code matching
        const refMatch = clean.match(/(?:پیگیری|رهگیری|مرجع|ارجاع|کد)\s*[:=]?\s*([0-9a-zA-Z]{5,15})/i);
        if (refMatch) {
            result.trackingCode = refMatch[1];
        }

        return result;
    }

    static detectBank(text, sender) {
        if (text.includes('ملت') || sender.includes('Mellat')) return { name: 'بانک ملت', color: '#dc2626', code: 'mellat' };
        if (text.includes('سامان') || text.includes('بلو') || sender.includes('Saman') || sender.includes('Blu')) return { name: 'بلو بانک (سامان)', color: '#0284c7', code: 'blubank' };
        if (text.includes('پاسارگاد') || sender.includes('Pasargad')) return { name: 'بانک پاسارگاد', color: '#f59e0b', code: 'pasargad' };
        if (text.includes('ملی') || sender.includes('Melli')) return { name: 'بانک ملی ایران', color: '#1e3a8a', code: 'melli' };
        if (text.includes('سپه') || sender.includes('Sepah')) return { name: 'بانک سپه', color: '#047857', code: 'sepah' };
        if (text.includes('تجارت') || sender.includes('Tejarat')) return { name: 'بانک تجارت', color: '#1d4ed8', code: 'tejarat' };
        if (text.includes('صادرات') || sender.includes('Saderat')) return { name: 'بانک صادرات', color: '#4338ca', code: 'saderat' };
        if (text.includes('رسالت')) return { name: 'بانک رسالت', color: '#0f766e', code: 'resalat' };
        return { name: 'شبکه شتاب', color: '#6366f1', code: 'shetab' };
    }
}
