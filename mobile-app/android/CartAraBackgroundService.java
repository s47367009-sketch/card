package com.cartara.companion.services;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.Intent;
import android.os.Build;
import android.os.IBinder;
import androidx.core.app.NotificationCompat;
import com.cartara.companion.MainActivity;

/**
 * Foreground Service for CartAra Pro Mobile App
 * Keeps the SMS Listener alive even when app is killed or device is sleeping.
 */
public class CartAraBackgroundService extends Service {
    private static final String CHANNEL_ID = "CartAraMonitorChannel";
    private static final int NOTIFICATION_ID = 9001;

    @Override
    public void onCreate() {
        super.onCreate();
        createNotificationChannel();
    }

    @Override
    public int onStartCommand(Intent intent, int flags, int startId) {
        Intent notificationIntent = new Intent(this, MainActivity.class);
        PendingIntent pendingIntent = PendingIntent.getActivity(
                this,
                0,
                notificationIntent,
                PendingIntent.FLAG_IMMUTABLE | PendingIntent.FLAG_UPDATE_CURRENT
        );

        Notification notification = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setContentTitle("کارت‌آرا پرو | مانیتورینگ خودکار فعال است")
                .setContentText("پایش پیامک‌های بانکی جهت تایید آنی سفارشات در حال اجراست...")
                .setSmallIcon(android.R.drawable.stat_notify_sync)
                .setContentIntent(pendingIntent)
                .setOngoing(true)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .build();

        startForeground(NOTIFICATION_ID, notification);
        return START_STICKY;
    }

    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    CHANNEL_ID,
                    "پایشگر پیامک کارت‌آرا",
                    NotificationManager.IMPORTANCE_LOW
            );
            channel.setDescription("کانال اعلان‌های پس‌زمینه برای حفظ اجرای پایشگر پیامک‌های بانکی");
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) {
                manager.createNotificationChannel(channel);
            }
        }
    }
}
