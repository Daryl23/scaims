<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Grade Evaluation</title>
</head>
<body>

    <h1>Student Grade Evaluation System</h1>

    <form action="process.php" method="post">

        <label for="student_name">Student Name:</label>
        <input
            type="text"
            id="student_name"
            name="student_name"
            required
        >

        <br><br>

        <label for="programming">Programming:</label>
        <input
            type="number"
            id="programming"
            name="programming"
            min="0"
            max="100"
            required
        >

        <br><br>

        <label for="database">Database:</label>
        <input
            type="number"
            id="database"
            name="database"
            min="0"
            max="100"
            required
        >

        <br><br>

        <label for="web">Web Development:</label>
        <input
            type="number"
            id="web"
            name="web"
            min="0"
            max="100"
            required
        >

        <br><br>

        <label for="networking">Networking:</label>
        <input
            type="number"
            id="networking"
            name="networking"
            min="0"
            max="100"
            required
        >

        <br><br>

        <button type="submit">Calculate Grade</button>

    </form>

</body>
</html>
