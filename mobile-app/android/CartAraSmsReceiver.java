package com.cartara.companion.services;

import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.os.Bundle;
import android.telephony.SmsMessage;
import android.util.Log;
import org.json.JSONObject;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;

/**
 * High Performance SMS Broadcast Receiver for CartAra Pro
 * Listens to incoming SMS from Iranian Banks, parses deposits and triggers webhook.
 */
public class CartAraSmsReceiver extends BroadcastReceiver {
    private static final String TAG = "CartAraSmsReceiver";

    @Override
    public void onReceive(Context context, Intent intent) {
        if ("android.provider.Telephony.SMS_RECEIVED".equals(intent.getAction())) {
            Bundle bundle = intent.getExtras();
            if (bundle != null) {
                Object[] pdus = (Object[]) bundle.get("pdus");
                if (pdus != null) {
                    for (Object pdu : pdus) {
                        SmsMessage sms = SmsMessage.createFromPdu((byte[]) pdu);
                        String sender = sms.getDisplayOriginatingAddress();
                        String messageBody = sms.getMessageBody();

                        Log.d(TAG, "SMS Received from: " + sender);

                        // Filter Bank SMS only
                        if (isBankTransactionSms(messageBody)) {
                            forwardSmsToWordPress(context, sender, messageBody);
                        }
                    }
                }
            }
        }
    }

    private boolean isBankTransactionSms(String body) {
        String lower = body.toLowerCase();
        // Ignore OTP / 2FA SMS
        if (lower.contains("کد تایید") || lower.contains("رمز یکبار مصرف") || lower.contains("رمز پویا")) {
            return false;
        }
        return body.contains("واریز") || body.contains("واریزبه") || body.contains("انتقال") || body.contains("پایا") || body.contains("ساتنا") || body.contains("افزایش");
    }

    private void forwardSmsToWordPress(Context context, String sender, String messageBody) {
        new Thread(() -> {
            try {
                // Fetch saved store webhook & token from SharedPreferences
                String webhookUrl = context.getSharedPreferences("CartAraPrefs", Context.MODE_PRIVATE)
                        .getString("webhook_url", "");
                String authToken = context.getSharedPreferences("CartAraPrefs", Context.MODE_PRIVATE)
                        .getString("auth_token", "");

                if (webhookUrl.isEmpty() || authToken.isEmpty()) {
                    Log.w(TAG, "Webhook URL or Token not configured in CartAra App");
                    return;
                }

                URL url = new URL(webhookUrl);
                HttpURLConnection conn = (HttpURLConnection) url.openConnection();
                conn.setRequestMethod("POST");
                conn.setRequestProperty("Content-Type", "application/json; charset=UTF-8");
                conn.setRequestProperty("X-CartAra-Token", authToken);
                conn.setDoOutput(true);
                conn.setConnectTimeout(10000);
                conn.setReadTimeout(10000);

                JSONObject payload = new JSONObject();
                payload.put("sms_body", messageBody);
                payload.put("sender", sender);
                payload.put("timestamp", System.currentTimeMillis());

                OutputStream os = conn.getOutputStream();
                os.write(payload.toString().getBytes("UTF-8"));
                os.close();

                int responseCode = conn.getResponseCode();
                Log.d(TAG, "Forwarded SMS, Response Code: " + responseCode);
                conn.disconnect();

            } catch (Exception e) {
                Log.e(TAG, "Error forwarding SMS to WordPress", e);
            }
        }).start();
    }
}
