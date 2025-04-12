<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Access Restricted - Play a Game!</title>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
        }
        .game-container {
            max-width: 600px;
            margin: 50px auto;
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .message {
            color: #e74c3c;
            margin-bottom: 20px;
            padding: 15px;
            background: #fde8e8;
            border-radius: 8px;
            font-size: 1.1em;
        }
        .game-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px auto;
            max-width: 300px;
        }
        .cell {
            aspect-ratio: 1;
            background: #eef2ff;
            border: none;
            border-radius: 8px;
            font-size: 2em;
            font-weight: bold;
            color: #4f46e5;
            cursor: pointer;
            transition: all 0.2s;
        }
        .cell:hover {
            background: #e0e7ff;
            transform: scale(1.05);
        }
        #status {
            margin: 20px 0;
            font-size: 1.2em;
            color: #4f46e5;
            font-weight: 500;
        }
        .reset-btn {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 1em;
            cursor: pointer;
            transition: background 0.2s;
        }
        .reset-btn:hover {
            background: #4338ca;
        }
        .back-btn {
            margin-top: 20px;
            background: #6b7280;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: background 0.2s;
        }
        .back-btn:hover {
            background: #4b5563;
        }
    </style>
</head>
<body>
    <div class="game-container">
        <div class="message">
            Cette page est réservée aux enseignants. En attendant, profitez d'une partie de Morpion !
        </div>
        <div id="game-board" class="game-board">
            <?php for($i = 0; $i < 9; $i++): ?>
                <button class="cell" data-index="<?php echo $i; ?>"></button>
            <?php endfor; ?>
        </div>
        <div id="status"></div>
        <button onclick="resetGame()" class="reset-btn">Nouvelle Partie</button>
        <br>
        <a href="index.php" class="back-btn">Retour à l'accueil</a>
    </div>

    <script>
        let currentPlayer = 'X';
        let gameBoard = ['', '', '', '', '', '', '', '', ''];
        let gameActive = true;

        document.querySelectorAll('.cell').forEach(cell => {
            cell.addEventListener('click', () => handleCellClick(cell));
        });

        function handleCellClick(cell) {
            const index = cell.getAttribute('data-index');
            if (gameBoard[index] === '' && gameActive) {
                gameBoard[index] = currentPlayer;
                cell.textContent = currentPlayer;
                
                if (checkWinner()) {
                    document.getElementById('status').textContent = `Joueur ${currentPlayer} gagne !`;
                    gameActive = false;
                } else if (!gameBoard.includes('')) {
                    document.getElementById('status').textContent = "Match nul !";
                    gameActive = false;
                } else {
                    currentPlayer = currentPlayer === 'X' ? 'O' : 'X';
                    if (currentPlayer === 'O') {
                        setTimeout(computerMove, 500);
                    }
                }
            }
        }

        function computerMove() {
            if (!gameActive) return;
            
            let emptySpots = gameBoard.reduce((acc, val, idx) => {
                if (val === '') acc.push(idx);
                return acc;
            }, []);
            
            if (emptySpots.length > 0) {
                const randomSpot = emptySpots[Math.floor(Math.random() * emptySpots.length)];
                gameBoard[randomSpot] = currentPlayer;
                document.querySelector(`[data-index="${randomSpot}"]`).textContent = currentPlayer;
                
                if (checkWinner()) {
                    document.getElementById('status').textContent = `Joueur ${currentPlayer} gagne !`;
                    gameActive = false;
                } else if (!gameBoard.includes('')) {
                    document.getElementById('status').textContent = "Match nul !";
                    gameActive = false;
                } else {
                    currentPlayer = 'X';
                }
            }
        }

        function checkWinner() {
            const winPatterns = [
                [0, 1, 2], [3, 4, 5], [6, 7, 8],
                [0, 3, 6], [1, 4, 7], [2, 5, 8],
                [0, 4, 8], [2, 4, 6]
            ];

            return winPatterns.some(pattern => {
                return pattern.every(index => {
                    return gameBoard[index] === currentPlayer;
                });
            });
        }

        function resetGame() {
            gameBoard = ['', '', '', '', '', '', '', '', ''];
            gameActive = true;
            currentPlayer = 'X';
            document.querySelectorAll('.cell').forEach(cell => cell.textContent = '');
            document.getElementById('status').textContent = '';
        }
    </script>
</body>
</html>