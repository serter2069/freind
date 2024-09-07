<?php
// Страница, информирующая пользователей о сборе и использовании данных

// Функция для отображения контента страницы
function displayContent() {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>User Data Usage</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f4f4f9;
                color: #333;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 1000px;
                margin: 0 auto;
                background-color: #fff;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            }
            h1 {
                color: #2c3e50;
            }
            p {
                font-size: 18px;
                line-height: 1.6;
            }
            ul {
                font-size: 18px;
                margin-left: 20px;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>How We Use Your Data for Optimal User Matching</h1>
            <p>Our platform is committed to providing you with the best possible user experience. To achieve this, we use data collected through your interactions with Google and YouTube. This data helps us create personalized matches, ensuring that the users you are connected with are aligned with your preferences and needs.</p>

            <h2>Data We Collect</h2>
            <p>The following types of data may be collected from you:</p>
            <ul>
                <li><strong>Google Account Information:</strong> We collect your Google profile data, including your name, email address, and profile picture, to verify your identity and provide customized services.</li>
                <li><strong>YouTube Activity:</strong> Information about your YouTube watch history, likes, and subscriptions may be used to enhance recommendations and improve user matching accuracy.</li>
                <li><strong>Location Data:</strong> We may use location data to match you with users in your area for better service delivery and local opportunities.</li>
                <li><strong>Behavioral Data:</strong> Information about your interactions on our platform, such as preferences and activity patterns, is used to optimize your user experience.</li>
            </ul>

            <h2>How Your Data is Used</h2>
            <p>We use your data for the following purposes:</p>
            <ul>
                <li><strong>Personalized Matches:</strong> Your data allows us to provide better user matches based on your preferences, habits, and activity. This creates a more meaningful and relevant experience for you.</li>
                <li><strong>Improved Recommendations:</strong> By analyzing your YouTube activity, we can offer more accurate recommendations of users who share similar interests.</li>
                <li><strong>Security and Authentication:</strong> Your Google account information helps us ensure that the platform remains secure and free of unauthorized access.</li>
                <li><strong>Service Enhancements:</strong> We continuously analyze user behavior and feedback to improve the overall quality of our service, introducing new features and optimizing performance.</li>
            </ul>

            <h2>Your Privacy</h2>
            <p>Your privacy is of utmost importance to us. All data collected is used in accordance with privacy regulations and will not be shared with third parties without your consent.</p>
            <p>By using our platform, you agree to our collection and use of your data as outlined above. If you have any concerns about your privacy, you can contact our support team for more information.</p>
        </div>
    </body>
    </html>
    ';
}

// Вызов функции для отображения контента
displayContent();
?>
