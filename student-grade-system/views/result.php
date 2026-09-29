<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Result</title>
</head>
<body>

    <h1>Student Result</h1>

    <p>
        Name:
        <?php echo htmlspecialchars($student->name); ?>
    </p>

    <h2>Grades</h2>

    <ul>
        <?php foreach ($student->grades as $subject => $grade): ?>
            <li>
                <?php echo htmlspecialchars($subject); ?>:
                <?php echo $grade; ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <p>
        Average:
        <?php echo number_format($student->average, 2); ?>
    </p>

    <p>
        Remark:
        <?php echo htmlspecialchars($student->remark); ?>
    </p>

    <p>
        Status:
        <?php echo htmlspecialchars($student->status); ?>
    </p>

    <a href="index.php">Evaluate Another Student</a>

</body>
</html>
