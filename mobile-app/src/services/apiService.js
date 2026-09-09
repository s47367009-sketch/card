/**
 * CartAra Mobile API Service
 * Manages communication with WordPress REST API
 */

export class ApiService {
    static async forwardSms(storeConfig, smsData) {
        try {
            const response = await fetch(`${storeConfig.url}/wp-json/cartara/v1/sms-webhook`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CartAra-Token': storeConfig.token
                },
                body: JSON.stringify({
                    sms_body: smsData.rawText,
                    sender: smsData.bank.name,
                    timestamp: Date.now()
                })
            });

            const data = await response.json();
            return { success: true, data };
        } catch (error) {
            console.error('API Error forwarding SMS:', error);
            return { success: false, error: error.message };
        }
    }

    static async verifyOrder(storeConfig, orderId, status, receiptId = 0) {
        try {
            const response = await fetch(`${storeConfig.url}/wp-json/cartara/v1/verify-manual`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CartAra-Token': storeConfig.token
                },
                body: JSON.stringify({
                    order_id: orderId,
                    status: status,
                    receipt_id: receiptId
                })
            });
            return await response.json();
        } catch (error) {
            return { success: false, error: error.message };
        }
    }
}
