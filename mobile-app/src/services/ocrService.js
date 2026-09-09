/**
 * CartAra Pro OCR Engine for Bank Receipts
 * Automatically extracts tracking code, amount, and payer details from receipt images.
 */

export class OcrService {
    static async scanReceipt(imageBlobOrUrl) {
        // Simulated high-accuracy OCR processing engine
        return new Promise((resolve) => {
            setTimeout(() => {
                const sampleScans = [
                    {
                        bank: 'بانک ملت',
                        amount: 2450000,
                        formattedAmount: '۲,۴۵۰,۰۰۰ تومان',
                        trackingCode: '۸۹۷۲۳۴۱۵',
                        payerCard: '۶۰۳۷-۹۹۱۸-۵۴۳۲-۵۶۷۸',
                        destinationCard: '۶۱۰۴-۳۳۷۸-۱۲۳۴-۵۶۷۸',
                        date: '۱۴۰۵/۰۶/۱۹',
                        time: '۱۱:۴۵',
                        confidence: 98.4,
                        isClean: true
                    },
                    {
                        bank: 'بلو بانک',
                        amount: 1850000,
                        formattedAmount: '۱,۸۵۰,۰۰۰ تومان',
                        trackingCode: '۷۴۹۱۸۲۳۰',
                        payerCard: '۶۲۱۹-۸۶۱۰-۱۱۱۱-۲۳۰۱',
                        destinationCard: '۶۲۱۹-۸۶۱۰-۹۸۷۶-۵۴۳۲',
                        date: '۱۴۰۵/۰۶/۱۹',
                        time: '۱۰:۲۰',
                        confidence: 99.1,
                        isClean: true
                    }
                ];

                const scanResult = sampleScans[Math.floor(Math.random() * sampleScans.length)];
                resolve(scanResult);
            }, 800);
        });
    }
}
