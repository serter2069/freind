<?php 

 include 'header.php'; ?>
<div class="contact-page">
    <div class="container">
        <h1 class="text-center mb-5">Get in Touch</h1>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="contact-buttons">
                <a href="https://t.me/dontsurrender" target="_blank" class="contact-button telegram">
                    <i class="fab fa-telegram-plane"></i>
                    <span class="button-text">
                        <strong>Telegram</strong>
                        <small>@dontsurrender</small>
                    </span>
                </a>
                
                <div class="contact-button email" onclick="copyEmail()">
                    <i class="far fa-envelope"></i>
                    <span class="button-text">
                        <strong>Email</strong>
                        <small id="emailAddress">serter2069@gmail.com</small>
                    </span>
                </div>
                
                <a href="https://wa.me/17024826083" target="_blank" class="contact-button whatsapp">
                    <i class="fab fa-whatsapp"></i>
                    <span class="button-text">
                        <strong>WhatsApp</strong>
                        <small>+1 (702) 482-6083</small>
                    </span>
                </a>
            </div>
        </div>
    </div>
</div>
</div>
<div id="notification" class="notification">Email copied to clipboard!</div>
<style>
    .contact-page {
        /* background-color: #f0f2f5; */
        padding: 80px 0;
        min-height: calc(100vh - 56px);
    }

    h1 {
        color: #333;
        font-weight: 700;
        font-size: 3rem;
        margin-bottom: 50px;
    }

    .contact-buttons {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .contact-button {
        display: flex;
        align-items: center;
        padding: 20px;
        border-radius: 15px;
        text-decoration: none;
        color: #fff;
        font-size: 1.2rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        cursor: pointer;
    }

    .contact-button:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        color: #fff;
        text-decoration: none;
    }

    .contact-button i {
        font-size: 2.5rem;
        margin-right: 20px;
    }

    .button-text {
        display: flex;
        flex-direction: column;
    }

    .button-text strong {
        font-size: 1.4rem;
        margin-bottom: 5px;
    }

    .button-text small {
        font-size: 1rem;
        opacity: 0.9;
    }

    .telegram {
        background-color: #0088cc;
    }

    .email {
        background-color: #ff4f4f;
    }

    .whatsapp {
        background-color: #25D366;
    }

    .notification {
        position: fixed;
        bottom: 20px;
        left: 50%;
        transform: translateX(-50%);
        background-color: #333;
        color: #fff;
        padding: 10px 20px;
        border-radius: 5px;
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    @media (max-width: 768px) {
        .contact-button {
            flex-direction: column;
            text-align: center;
            padding: 30px;
        }

        .contact-button i {
            margin-right: 0;
            margin-bottom: 15px;
        }
    }
</style>
<script>
function copyEmail() {
    var email = document.getElementById("emailAddress").innerText;
    navigator.clipboard.writeText(email).then(function() {
        showNotification();
    }, function(err) {
        console.error('Could not copy text: ', err);
    });
}

function showNotification() {
    var notification = document.getElementById("notification");
    notification.style.display = "block";
    setTimeout(function() {
        notification.style.opacity = "1";
    }, 10);
    setTimeout(function() {
        notification.style.opacity = "0";
        setTimeout(function() {
            notification.style.display = "none";
        }, 300);
    }, 2000);
}
</script>
<?php include 'footer.php'; ?>