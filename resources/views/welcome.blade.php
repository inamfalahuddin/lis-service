<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RS Ananda Group - Web Service</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 50px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .logo {
            margin-bottom: 30px;
        }

        .logo h1 {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .logo h2 {
            color: #667eea;
            font-size: 1.5rem;
            margin-bottom: 5px;
            font-weight: 600;
        }

        .logo .subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }

        .version {
            background: #f8f9fa;
            padding: 8px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 30px;
            color: #667eea;
            font-weight: 600;
            border: 2px solid #e9ecef;
        }

        .welcome-message {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #2c3e50;
            border: 2px solid #e9ecef;
        }

        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-2px);
        }

        .status {
            margin-top: 40px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #28a745;
        }

        .status h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .status p {
            color: #28a745;
            font-weight: 600;
        }

        .footer {
            margin-top: 40px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
            }

            .logo h1 {
                font-size: 2rem;
            }

            .logo h2 {
                font-size: 1.3rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo">
            {{-- <h1>RS Ananda Group</h1> --}}
            <img src="{{ asset('img/logo-rsa-group.png') }}" alt="RS Ananda Group" style="width: 200px;">
            <h2>SOFTMEDIX LABORATORIUM</h2>
            <div class="subtitle">Web Service Platform</div>
            <div class="version">Ver.003.1</div>
        </div>

        <div class="welcome-message">
            Selamat datang di Web Service RS Ananda Group.
            Platform terintegrasi untuk manajemen data rumah sakit yang modern dan efisien.
        </div>

        <div class="btn-group">
            <a href="/docs" class="btn btn-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14,2 14,8 20,8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10,9 9,9 8,9"></polyline>
                </svg>
                Dokumentasi API
            </a>
            <a href="/health" class="btn btn-secondary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22,4 12,14.01 9,11.01"></polyline>
                </svg>
                Status Sistem
            </a>
        </div>

        <div class="status">
            <h3>🟢 Sistem Berjalan Normal</h3>
            <p>Semua layanan beroperasi dengan baik</p>
        </div>

        <div class="footer">
            &copy; 2025 RS Ananda Group. All rights reserved.
        </div>
    </div>

    <script>
        // Animasi sederhana untuk tombol
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Tambahkan efek ketik untuk welcome message
            const welcomeMessage = document.querySelector('.welcome-message');
            const originalText = welcomeMessage.textContent;
            welcomeMessage.textContent = '';
            
            let i = 0;
            function typeWriter() {
                if (i < originalText.length) {
                    welcomeMessage.textContent += originalText.charAt(i);
                    i++;
                    setTimeout(typeWriter, 50);
                }
            }
            
            // Mulai efek ketik setelah 500ms
            setTimeout(typeWriter, 500);
        });
    </script>
</body>

</html>