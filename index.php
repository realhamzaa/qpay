<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>QPay | رحلتك المالية تبدأ هنا</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .hero-container {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            gap: 4rem;
        }

        .hero-content {
            text-align: center;
            max-width: 500px;
        }

        @media (max-width: 1023px) {
            .hero-container {
                flex-direction: column;
                justify-content: center;
                gap: 2rem;
                padding: 1.5rem;
            }
            .hero-content {
                order: -1;
            }
        }

        .bg-glow {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(0, 122, 255, 0.08) 0%, transparent 70%);
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <div class="bg-glow"></div>
    <div class="hero-container">
        
        <!-- Left Side: Branding -->
        <div class="hero-content fade-in">
            <div class="logo-circle" style="width: 80px; height: 80px; border-radius: 20px; font-size: 2.5rem; margin: 0 auto 1.5rem; box-shadow: 0 10px 30px rgba(0, 122, 255, 0.3);">Q</div>
            <h1 style="font-size: 3.5rem; font-weight: 800; letter-spacing: -2px; margin-bottom: 0.5rem; line-height: 1;">QPAY</h1>
            <p style="color: var(--ios-gray); font-size: 1.2rem; font-weight: 500; margin-top: 1rem;">المستقبل بين يديك، بكل بساطة.</p>
            <div class="desktop-only" style="margin-top: 3rem; color: var(--ios-gray); font-size: 0.8rem; letter-spacing: 3px; opacity: 0.5;">
                SECURE • FAST • RELIABLE
            </div>
        </div>

        <!-- Right Side: Action Card -->
        <div class="glass-card auth-card fade-in" style="width: 100%; max-width: 420px; padding: 2.5rem;">
            <p style="text-align: center; color: var(--text-main); font-size: 1rem; margin-bottom: 2.5rem; line-height: 1.8;">
                أهلاً بك في الجيل القادم من الخدمات المالية الرقمية. أمان مطلق، سرعة فائقة، وتصميم مريح.
            </p>
            
            <?php if (isset($_GET['pending'])): ?>
                <div class="ios-alert" style="color: var(--ios-green); margin-bottom: 2rem;">
                    <strong>تم إرسال طلبك!</strong><br>
                    حسابك الآن قيد المراجعة الفنية.
                </div>
            <?php endif; ?>

            <div style="display: grid; gap: 1rem;">
                <a href="login.php" class="btn btn-primary" style="padding: 1.2rem; font-size: 1.1rem;">دخول المحطة</a>
                <a href="register.php" class="btn" style="background: rgba(255,255,255,0.05); color: #fff; padding: 1.1rem; border: 0.5px solid var(--ios-border);">إنشاء حساب جديد</a>
            </div>
        </div>

    </div>

    <div style="position: fixed; bottom: 2rem; width: 100%; text-align: center; color: var(--ios-gray); font-size: 0.7rem; letter-spacing: 2px; opacity: 0.5;">
        DESIGNED BY ANTIGRAVITY IN CUPERTINO
    </div>
</body>
</html>
