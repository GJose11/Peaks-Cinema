<?php
    $servername = "localhost";
    $username = "root";
    $password = "";

    $conn = new mysqli($servername, $username, $password);

    if($_SERVER["REQUEST_METHOD"] == "POST") {
        $sql = <<<SQL
        SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
        SET time_zone = "+00:00";

        /*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
        /*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
        /*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
        /*!40101 SET NAMES utf8mb4 */;

        DROP DATABASE IF EXISTS `peakscinemadb`;
        CREATE DATABASE IF NOT EXISTS `peakscinemadb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
        USE `peakscinemadb`;

        CREATE TABLE `customer` (
        `Customer_ID` int(11) NOT NULL,
        `Name` varchar(100) NOT NULL,
        `Email` varchar(100) NOT NULL,
        `Password` varchar(255) NOT NULL,
        `ProfilePhoto` text DEFAULT NULL,
        `CountryCode` varchar(10) DEFAULT NULL,
        `PhoneNumber` varchar(15) DEFAULT NULL,
        `PaymentMethod` tinytext DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `e-receipt` (
        `Receipt_ID` int(11) NOT NULL,
        `PaymentID` int(11) NOT NULL,
        `DateIssued` date NOT NULL,
        `SentToEmail` varchar(100) NOT NULL,
        `ReceiptStatus` varchar(20) NOT NULL DEFAULT 'Issued'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `mall` (
        `Mall_ID` int(11) NOT NULL,
        `MallName` tinytext NOT NULL,
        `Location` tinytext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        INSERT INTO `mall` (`Mall_ID`, `MallName`, `Location`) VALUES
        (1, 'SM Marikina', 'Marcos Highway, Calumpang, Marikina City, 1801, Marikina, Luzon Philippines');

        CREATE TABLE `movie` (
        `Movie_ID` int(11) NOT NULL,
        `MovieName` text NOT NULL,
        `MovieDescription` mediumtext NOT NULL,
        `Genre` tinytext NOT NULL,
        `Rating` varchar(10) NOT NULL,
        `Runtime` int(11) NOT NULL,
        `MoviePoster` text NOT NULL,
        `MovieAvailability` tinytext NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        INSERT INTO `movie` (`Movie_ID`, `MovieName`, `MovieDescription`, `Genre`, `Rating`, `Runtime`, `MoviePoster`, `MovieAvailability`) VALUES
        (1, 'Superman', 'Superman must reconcile his alien Kryptonian heritage with his human upbringing as reporter Clark Kent. As the embodiment of truth, justice and the human way he soon finds himself in a world that views these as old-fashioned.\r\n\r\n', 'Superhero, Action', 'PG', 129, 'PeaksCinema/MoviePosters/Superman.png', 'Now Showing');

        CREATE TABLE `payment` (
        `Payment_ID` int(11) NOT NULL,
        `Ticket_ID` int(11) NOT NULL,
        `PaymentMethod` varchar(50) NOT NULL,
        `AmountPaid` decimal(10,2) NOT NULL,
        `PaymentDate` date NOT NULL,
        `PaymentStatus` varchar(20) NOT NULL DEFAULT 'Paid'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `seats` (
        `Seat_ID` int(11) NOT NULL,
        `SeatRow` varchar(10) NOT NULL,
        `SeatColumn` varchar(10) NOT NULL,
        `SeatType` varchar(50) NOT NULL,
        `SeatAvailability` tinyint(1) NOT NULL DEFAULT 1,
        `SeatPrice` decimal(10,2) NOT NULL DEFAULT 0.00,
        `Theater_ID` int(11) NOT NULL,
        `TimeSlot_ID` int(11) DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        INSERT INTO `seats` (`Seat_ID`, `SeatRow`, `SeatColumn`, `SeatType`, `SeatAvailability`, `SeatPrice`, `Theater_ID`, `TimeSlot_ID`) VALUES
        (353, 'A', '10', 'Regular', 0, NULL, 13, NULL),
        (354, 'A', '9', 'Regular', 0, NULL, 13, NULL),
        (355, 'A', '0', 'Empty', 0, NULL, 13, NULL),
        (356, 'A', '0', 'Empty', 0, NULL, 13, NULL),
        (357, 'A', '8', 'Regular', 0, NULL, 13, NULL),
        (358, 'A', '7', 'Regular', 0, NULL, 13, NULL),
        (359, 'A', '6', 'Regular', 0, NULL, 13, NULL),
        (360, 'A', '5', 'Regular', 0, NULL, 13, NULL),
        (361, 'A', '4', 'Regular', 0, NULL, 13, NULL),
        (362, 'A', '3', 'Regular', 0, NULL, 13, NULL),
        (363, 'A', '0', 'Empty', 0, NULL, 13, NULL),
        (364, 'A', '0', 'Empty', 0, NULL, 13, NULL),
        (365, 'A', '2', 'Regular', 0, NULL, 13, NULL),
        (366, 'A', '1', 'Regular', 0, NULL, 13, NULL),
        (367, 'B', '10', 'Regular', 0, NULL, 13, NULL),
        (368, 'B', '9', 'Regular', 0, NULL, 13, NULL),
        (369, 'B', '0', 'Empty', 0, NULL, 13, NULL),
        (370, 'B', '0', 'Empty', 0, NULL, 13, NULL),
        (371, 'B', '8', 'Regular', 0, NULL, 13, NULL),
        (372, 'B', '7', 'Regular', 0, NULL, 13, NULL),
        (373, 'B', '6', 'Regular', 0, NULL, 13, NULL),
        (374, 'B', '5', 'Regular', 0, NULL, 13, NULL),
        (375, 'B', '4', 'Regular', 0, NULL, 13, NULL),
        (376, 'B', '3', 'Regular', 0, NULL, 13, NULL),
        (377, 'B', '0', 'Empty', 0, NULL, 13, NULL),
        (378, 'B', '0', 'Empty', 0, NULL, 13, NULL),
        (379, 'B', '2', 'Regular', 0, NULL, 13, NULL),
        (380, 'B', '1', 'Regular', 0, NULL, 13, NULL),
        (381, 'C', '10', 'Regular', 0, NULL, 13, NULL),
        (382, 'C', '9', 'Regular', 0, NULL, 13, NULL),
        (383, 'C', '0', 'Empty', 0, NULL, 13, NULL),
        (384, 'C', '0', 'Empty', 0, NULL, 13, NULL),
        (385, 'C', '8', 'Regular', 0, NULL, 13, NULL),
        (386, 'C', '7', 'Regular', 0, NULL, 13, NULL),
        (387, 'C', '6', 'Regular', 0, NULL, 13, NULL),
        (388, 'C', '5', 'Regular', 0, NULL, 13, NULL),
        (389, 'C', '4', 'Regular', 0, NULL, 13, NULL),
        (390, 'C', '3', 'Regular', 0, NULL, 13, NULL),
        (391, 'C', '0', 'Empty', 0, NULL, 13, NULL),
        (392, 'C', '0', 'Empty', 0, NULL, 13, NULL),
        (393, 'C', '2', 'Regular', 0, NULL, 13, NULL),
        (394, 'C', '1', 'Regular', 0, NULL, 13, NULL),
        (395, 'D', '10', 'Regular', 0, NULL, 13, NULL),
        (396, 'D', '9', 'Regular', 0, NULL, 13, NULL),
        (397, 'D', '0', 'Empty', 0, NULL, 13, NULL),
        (398, 'D', '0', 'Empty', 0, NULL, 13, NULL),
        (399, 'D', '8', 'Regular', 0, NULL, 13, NULL),
        (400, 'D', '7', 'Regular', 0, NULL, 13, NULL),
        (401, 'D', '6', 'Regular', 0, NULL, 13, NULL),
        (402, 'D', '5', 'Regular', 0, NULL, 13, NULL),
        (403, 'D', '4', 'Regular', 0, NULL, 13, NULL),
        (404, 'D', '3', 'Regular', 0, NULL, 13, NULL),
        (405, 'D', '0', 'Empty', 0, NULL, 13, NULL),
        (406, 'D', '0', 'Empty', 0, NULL, 13, NULL),
        (407, 'D', '2', 'Regular', 0, NULL, 13, NULL),
        (408, 'D', '1', 'Regular', 0, NULL, 13, NULL),
        (409, 'E', '10', 'Regular', 0, NULL, 13, NULL),
        (410, 'E', '9', 'Regular', 0, NULL, 13, NULL),
        (411, 'E', '0', 'Empty', 0, NULL, 13, NULL),
        (412, 'E', '0', 'Empty', 0, NULL, 13, NULL),
        (413, 'E', '8', 'Regular', 0, NULL, 13, NULL),
        (414, 'E', '7', 'Regular', 0, NULL, 13, NULL),
        (415, 'E', '6', 'Regular', 0, NULL, 13, NULL),
        (416, 'E', '5', 'Regular', 0, NULL, 13, NULL),
        (417, 'E', '4', 'Regular', 0, NULL, 13, NULL),
        (418, 'E', '3', 'Regular', 0, NULL, 13, NULL),
        (419, 'E', '0', 'Empty', 0, NULL, 13, NULL),
        (420, 'E', '0', 'Empty', 0, NULL, 13, NULL),
        (421, 'E', '2', 'Regular', 0, NULL, 13, NULL),
        (422, 'E', '1', 'Regular', 0, NULL, 13, NULL),
        (493, 'A', '10', 'Regular', 1, '350', 13, 14),
        (494, 'A', '9', 'Regular', 1, '350', 13, 14),
        (495, 'A', '0', 'Empty', 0, '350', 13, 14),
        (496, 'A', '0', 'Empty', 1, '350', 13, 14),
        (497, 'A', '8', 'Regular', 1, '350', 13, 14),
        (498, 'A', '7', 'Regular', 1, '350', 13, 14),
        (499, 'A', '6', 'Regular', 1, '350', 13, 14),
        (500, 'A', '5', 'Regular', 1, '350', 13, 14),
        (501, 'A', '4', 'Regular', 1, '350', 13, 14),
        (502, 'A', '3', 'Regular', 1, '350', 13, 14),
        (503, 'A', '0', 'Empty', 1, '350', 13, 14),
        (504, 'A', '0', 'Empty', 1, '350', 13, 14),
        (505, 'A', '2', 'Regular', 1, '350', 13, 14),
        (506, 'A', '1', 'Regular', 0, '350', 13, 14),
        (507, 'B', '10', 'Regular', 1, '350', 13, 14),
        (508, 'B', '9', 'Regular', 1, '350', 13, 14),
        (509, 'B', '0', 'Empty', 1, '350', 13, 14),
        (510, 'B', '0', 'Empty', 1, '350', 13, 14),
        (511, 'B', '8', 'Regular', 1, '350', 13, 14),
        (512, 'B', '7', 'Regular', 1, '350', 13, 14),
        (513, 'B', '6', 'Regular', 1, '350', 13, 14),
        (514, 'B', '5', 'Regular', 1, '350', 13, 14),
        (515, 'B', '4', 'Regular', 1, '350', 13, 14),
        (516, 'B', '3', 'Regular', 1, '350', 13, 14),
        (517, 'B', '0', 'Empty', 1, '350', 13, 14),
        (518, 'B', '0', 'Empty', 1, '350', 13, 14),
        (519, 'B', '2', 'Regular', 1, '350', 13, 14),
        (520, 'B', '1', 'Regular', 1, '350', 13, 14),
        (521, 'C', '10', 'Regular', 1, '350', 13, 14),
        (522, 'C', '9', 'Regular', 1, '350', 13, 14),
        (523, 'C', '0', 'Empty', 1, '350', 13, 14),
        (524, 'C', '0', 'Empty', 1, '350', 13, 14),
        (525, 'C', '8', 'Regular', 1, '350', 13, 14),
        (526, 'C', '7', 'Regular', 1, '350', 13, 14),
        (527, 'C', '6', 'Regular', 1, '350', 13, 14),
        (528, 'C', '5', 'Regular', 1, '350', 13, 14),
        (529, 'C', '4', 'Regular', 1, '350', 13, 14),
        (530, 'C', '3', 'Regular', 1, '350', 13, 14),
        (531, 'C', '0', 'Empty', 1, '350', 13, 14),
        (532, 'C', '0', 'Empty', 1, '350', 13, 14),
        (533, 'C', '2', 'Regular', 1, '350', 13, 14),
        (534, 'C', '1', 'Regular', 1, '350', 13, 14),
        (535, 'D', '10', 'Regular', 1, '350', 13, 14),
        (536, 'D', '9', 'Regular', 1, '350', 13, 14),
        (537, 'D', '0', 'Empty', 1, '350', 13, 14),
        (538, 'D', '0', 'Empty', 1, '350', 13, 14),
        (539, 'D', '8', 'Regular', 1, '350', 13, 14),
        (540, 'D', '7', 'Regular', 1, '350', 13, 14),
        (541, 'D', '6', 'Regular', 1, '350', 13, 14),
        (542, 'D', '5', 'Regular', 1, '350', 13, 14),
        (543, 'D', '4', 'Regular', 1, '350', 13, 14),
        (544, 'D', '3', 'Regular', 1, '350', 13, 14),
        (545, 'D', '0', 'Empty', 1, '350', 13, 14),
        (546, 'D', '0', 'Empty', 1, '350', 13, 14),
        (547, 'D', '2', 'Regular', 1, '350', 13, 14),
        (548, 'D', '1', 'Regular', 1, '350', 13, 14),
        (549, 'E', '10', 'Regular', 1, '350', 13, 14),
        (550, 'E', '9', 'Regular', 1, '350', 13, 14),
        (551, 'E', '0', 'Empty', 1, '350', 13, 14),
        (552, 'E', '0', 'Empty', 1, '350', 13, 14),
        (553, 'E', '8', 'Regular', 1, '350', 13, 14),
        (554, 'E', '7', 'Regular', 1, '350', 13, 14),
        (555, 'E', '6', 'Regular', 1, '350', 13, 14),
        (556, 'E', '5', 'Regular', 1, '350', 13, 14),
        (557, 'E', '4', 'Regular', 1, '350', 13, 14),
        (558, 'E', '3', 'Regular', 1, '350', 13, 14),
        (559, 'E', '0', 'Empty', 1, '350', 13, 14),
        (560, 'E', '0', 'Empty', 1, '350', 13, 14),
        (561, 'E', '2', 'Regular', 1, '350', 13, 14),
        (562, 'E', '1', 'Regular', 1, '350', 13, 14),
        (563, 'A', '10', 'Regular', 1, '390', 13, 15),
        (564, 'A', '9', 'Regular', 1, '390', 13, 15),
        (565, 'A', '0', 'Empty', 1, '390', 13, 15),
        (566, 'A', '0', 'Empty', 1, '390', 13, 15),
        (567, 'A', '8', 'Regular', 1, '390', 13, 15),
        (568, 'A', '7', 'Regular', 1, '390', 13, 15),
        (569, 'A', '6', 'Regular', 1, '390', 13, 15),
        (570, 'A', '5', 'Regular', 1, '390', 13, 15),
        (571, 'A', '4', 'Regular', 1, '390', 13, 15),
        (572, 'A', '3', 'Regular', 1, '390', 13, 15),
        (573, 'A', '0', 'Empty', 1, '390', 13, 15),
        (574, 'A', '0', 'Empty', 1, '390', 13, 15),
        (575, 'A', '2', 'Regular', 1, '390', 13, 15),
        (576, 'A', '1', 'Regular', 1, '390', 13, 15),
        (577, 'B', '10', 'Regular', 1, '390', 13, 15),
        (578, 'B', '9', 'Regular', 1, '390', 13, 15),
        (579, 'B', '0', 'Empty', 1, '390', 13, 15),
        (580, 'B', '0', 'Empty', 1, '390', 13, 15),
        (581, 'B', '8', 'Regular', 1, '390', 13, 15),
        (582, 'B', '7', 'Regular', 1, '390', 13, 15),
        (583, 'B', '6', 'Regular', 1, '390', 13, 15),
        (584, 'B', '5', 'Regular', 1, '390', 13, 15),
        (585, 'B', '4', 'Regular', 1, '390', 13, 15),
        (586, 'B', '3', 'Regular', 1, '390', 13, 15),
        (587, 'B', '0', 'Empty', 1, '390', 13, 15),
        (588, 'B', '0', 'Empty', 1, '390', 13, 15),
        (589, 'B', '2', 'Regular', 1, '390', 13, 15),
        (590, 'B', '1', 'Regular', 1, '390', 13, 15),
        (591, 'C', '10', 'Regular', 1, '390', 13, 15),
        (592, 'C', '9', 'Regular', 1, '390', 13, 15),
        (593, 'C', '0', 'Empty', 1, '390', 13, 15),
        (594, 'C', '0', 'Empty', 1, '390', 13, 15),
        (595, 'C', '8', 'Regular', 1, '390', 13, 15),
        (596, 'C', '7', 'Regular', 1, '390', 13, 15),
        (597, 'C', '6', 'Regular', 1, '390', 13, 15),
        (598, 'C', '5', 'Regular', 1, '390', 13, 15),
        (599, 'C', '4', 'Regular', 1, '390', 13, 15),
        (600, 'C', '3', 'Regular', 1, '390', 13, 15),
        (601, 'C', '0', 'Empty', 1, '390', 13, 15),
        (602, 'C', '0', 'Empty', 1, '390', 13, 15),
        (603, 'C', '2', 'Regular', 1, '390', 13, 15),
        (604, 'C', '1', 'Regular', 1, '390', 13, 15),
        (605, 'D', '10', 'Regular', 1, '390', 13, 15),
        (606, 'D', '9', 'Regular', 1, '390', 13, 15),
        (607, 'D', '0', 'Empty', 1, '390', 13, 15),
        (608, 'D', '0', 'Empty', 1, '390', 13, 15),
        (609, 'D', '8', 'Regular', 1, '390', 13, 15),
        (610, 'D', '7', 'Regular', 1, '390', 13, 15),
        (611, 'D', '6', 'Regular', 1, '390', 13, 15),
        (612, 'D', '5', 'Regular', 1, '390', 13, 15),
        (613, 'D', '4', 'Regular', 1, '390', 13, 15),
        (614, 'D', '3', 'Regular', 1, '390', 13, 15),
        (615, 'D', '0', 'Empty', 1, '390', 13, 15),
        (616, 'D', '0', 'Empty', 1, '390', 13, 15),
        (617, 'D', '2', 'Regular', 1, '390', 13, 15),
        (618, 'D', '1', 'Regular', 1, '390', 13, 15),
        (619, 'E', '10', 'Regular', 1, '390', 13, 15),
        (620, 'E', '9', 'Regular', 1, '390', 13, 15),
        (621, 'E', '0', 'Empty', 1, '390', 13, 15),
        (622, 'E', '0', 'Empty', 1, '390', 13, 15),
        (623, 'E', '8', 'Regular', 1, '390', 13, 15),
        (624, 'E', '7', 'Regular', 1, '390', 13, 15),
        (625, 'E', '6', 'Regular', 1, '390', 13, 15),
        (626, 'E', '5', 'Regular', 1, '390', 13, 15),
        (627, 'E', '4', 'Regular', 1, '390', 13, 15),
        (628, 'E', '3', 'Regular', 1, '390', 13, 15),
        (629, 'E', '0', 'Empty', 1, '390', 13, 15),
        (630, 'E', '0', 'Empty', 1, '390', 13, 15),
        (631, 'E', '2', 'Regular', 1, '390', 13, 15),
        (632, 'E', '1', 'Regular', 1, '390', 13, 15),
        (633, 'A', '10', 'Regular', 1, '290', 13, 16),
        (634, 'A', '9', 'Regular', 1, '290', 13, 16),
        (635, 'A', '0', 'Empty', 1, '290', 13, 16),
        (636, 'A', '0', 'Empty', 1, '290', 13, 16),
        (637, 'A', '8', 'Regular', 1, '290', 13, 16),
        (638, 'A', '7', 'Regular', 1, '290', 13, 16),
        (639, 'A', '6', 'Regular', 1, '290', 13, 16),
        (640, 'A', '5', 'Regular', 1, '290', 13, 16),
        (641, 'A', '4', 'Regular', 1, '290', 13, 16),
        (642, 'A', '3', 'Regular', 1, '290', 13, 16),
        (643, 'A', '0', 'Empty', 1, '290', 13, 16),
        (644, 'A', '0', 'Empty', 1, '290', 13, 16),
        (645, 'A', '2', 'Regular', 1, '290', 13, 16),
        (646, 'A', '1', 'Regular', 1, '290', 13, 16),
        (647, 'B', '10', 'Regular', 1, '290', 13, 16),
        (648, 'B', '9', 'Regular', 1, '290', 13, 16),
        (649, 'B', '0', 'Empty', 1, '290', 13, 16),
        (650, 'B', '0', 'Empty', 1, '290', 13, 16),
        (651, 'B', '8', 'Regular', 1, '290', 13, 16),
        (652, 'B', '7', 'Regular', 1, '290', 13, 16),
        (653, 'B', '6', 'Regular', 1, '290', 13, 16),
        (654, 'B', '5', 'Regular', 1, '290', 13, 16),
        (655, 'B', '4', 'Regular', 1, '290', 13, 16),
        (656, 'B', '3', 'Regular', 1, '290', 13, 16),
        (657, 'B', '0', 'Empty', 1, '290', 13, 16),
        (658, 'B', '0', 'Empty', 1, '290', 13, 16),
        (659, 'B', '2', 'Regular', 1, '290', 13, 16),
        (660, 'B', '1', 'Regular', 1, '290', 13, 16),
        (661, 'C', '10', 'Regular', 1, '290', 13, 16),
        (662, 'C', '9', 'Regular', 1, '290', 13, 16),
        (663, 'C', '0', 'Empty', 1, '290', 13, 16),
        (664, 'C', '0', 'Empty', 1, '290', 13, 16),
        (665, 'C', '8', 'Regular', 1, '290', 13, 16),
        (666, 'C', '7', 'Regular', 1, '290', 13, 16),
        (667, 'C', '6', 'Regular', 1, '290', 13, 16),
        (668, 'C', '5', 'Regular', 1, '290', 13, 16),
        (669, 'C', '4', 'Regular', 1, '290', 13, 16),
        (670, 'A', '10', 'Regular', 1, '290', 13, 17),
        (671, 'C', '3', 'Regular', 1, '290', 13, 16),
        (672, 'A', '9', 'Regular', 1, '290', 13, 17),
        (673, 'C', '0', 'Empty', 1, '290', 13, 16),
        (674, 'A', '0', 'Empty', 1, '290', 13, 17),
        (675, 'C', '0', 'Empty', 1, '290', 13, 16),
        (676, 'A', '0', 'Empty', 1, '290', 13, 17),
        (677, 'C', '2', 'Regular', 1, '290', 13, 16),
        (678, 'A', '8', 'Regular', 1, '290', 13, 17),
        (679, 'C', '1', 'Regular', 1, '290', 13, 16),
        (680, 'A', '7', 'Regular', 1, '290', 13, 17),
        (681, 'D', '10', 'Regular', 1, '290', 13, 16),
        (682, 'A', '6', 'Regular', 1, '290', 13, 17),
        (683, 'D', '9', 'Regular', 1, '290', 13, 16),
        (684, 'A', '5', 'Regular', 1, '290', 13, 17),
        (685, 'D', '0', 'Empty', 1, '290', 13, 16),
        (686, 'A', '4', 'Regular', 1, '290', 13, 17),
        (687, 'D', '0', 'Empty', 1, '290', 13, 16),
        (688, 'A', '3', 'Regular', 1, '290', 13, 17),
        (689, 'D', '8', 'Regular', 1, '290', 13, 16),
        (690, 'A', '0', 'Empty', 1, '290', 13, 17),
        (691, 'D', '7', 'Regular', 1, '290', 13, 16),
        (692, 'A', '0', 'Empty', 1, '290', 13, 17),
        (693, 'D', '6', 'Regular', 1, '290', 13, 16),
        (694, 'A', '2', 'Regular', 1, '290', 13, 17),
        (695, 'D', '5', 'Regular', 1, '290', 13, 16),
        (696, 'A', '1', 'Regular', 1, '290', 13, 17),
        (697, 'D', '4', 'Regular', 1, '290', 13, 16),
        (698, 'B', '10', 'Regular', 1, '290', 13, 17),
        (699, 'D', '3', 'Regular', 1, '290', 13, 16),
        (700, 'B', '9', 'Regular', 1, '290', 13, 17),
        (701, 'D', '0', 'Empty', 1, '290', 13, 16),
        (702, 'B', '0', 'Empty', 1, '290', 13, 17),
        (703, 'D', '0', 'Empty', 1, '290', 13, 16),
        (704, 'B', '0', 'Empty', 1, '290', 13, 17),
        (705, 'D', '2', 'Regular', 1, '290', 13, 16),
        (706, 'B', '8', 'Regular', 1, '290', 13, 17),
        (707, 'D', '1', 'Regular', 1, '290', 13, 16),
        (708, 'B', '7', 'Regular', 1, '290', 13, 17),
        (709, 'E', '10', 'Regular', 1, '290', 13, 16),
        (710, 'B', '6', 'Regular', 1, '290', 13, 17),
        (711, 'E', '9', 'Regular', 1, '290', 13, 16),
        (712, 'B', '5', 'Regular', 1, '290', 13, 17),
        (713, 'E', '0', 'Empty', 1, '290', 13, 16),
        (714, 'B', '4', 'Regular', 1, '290', 13, 17),
        (715, 'E', '0', 'Empty', 1, '290', 13, 16),
        (716, 'B', '3', 'Regular', 1, '290', 13, 17),
        (717, 'E', '8', 'Regular', 1, '290', 13, 16),
        (718, 'B', '0', 'Empty', 1, '290', 13, 17),
        (719, 'E', '7', 'Regular', 1, '290', 13, 16),
        (720, 'B', '0', 'Empty', 1, '290', 13, 17),
        (721, 'E', '6', 'Regular', 1, '290', 13, 16),
        (722, 'B', '2', 'Regular', 1, '290', 13, 17),
        (723, 'E', '5', 'Regular', 1, '290', 13, 16),
        (724, 'B', '1', 'Regular', 1, '290', 13, 17),
        (725, 'E', '4', 'Regular', 1, '290', 13, 16),
        (726, 'C', '10', 'Regular', 1, '290', 13, 17),
        (727, 'E', '3', 'Regular', 1, '290', 13, 16),
        (728, 'C', '9', 'Regular', 1, '290', 13, 17),
        (729, 'E', '0', 'Empty', 1, '290', 13, 16),
        (730, 'C', '0', 'Empty', 1, '290', 13, 17),
        (731, 'E', '0', 'Empty', 1, '290', 13, 16),
        (732, 'C', '0', 'Empty', 1, '290', 13, 17),
        (733, 'E', '2', 'Regular', 1, '290', 13, 16),
        (734, 'C', '8', 'Regular', 1, '290', 13, 17),
        (735, 'E', '1', 'Regular', 1, '290', 13, 16),
        (736, 'C', '7', 'Regular', 1, '290', 13, 17),
        (737, 'C', '6', 'Regular', 1, '290', 13, 17),
        (738, 'C', '5', 'Regular', 1, '290', 13, 17),
        (739, 'C', '4', 'Regular', 1, '290', 13, 17),
        (740, 'C', '3', 'Regular', 1, '290', 13, 17),
        (741, 'C', '0', 'Empty', 1, '290', 13, 17),
        (742, 'C', '0', 'Empty', 1, '290', 13, 17),
        (743, 'C', '2', 'Regular', 1, '290', 13, 17),
        (744, 'C', '1', 'Regular', 1, '290', 13, 17),
        (745, 'D', '10', 'Regular', 1, '290', 13, 17),
        (746, 'D', '9', 'Regular', 1, '290', 13, 17),
        (747, 'D', '0', 'Empty', 1, '290', 13, 17),
        (748, 'D', '0', 'Empty', 1, '290', 13, 17),
        (749, 'D', '8', 'Regular', 1, '290', 13, 17),
        (750, 'D', '7', 'Regular', 1, '290', 13, 17),
        (751, 'D', '6', 'Regular', 1, '290', 13, 17),
        (752, 'D', '5', 'Regular', 1, '290', 13, 17),
        (753, 'D', '4', 'Regular', 1, '290', 13, 17),
        (754, 'D', '3', 'Regular', 1, '290', 13, 17),
        (755, 'D', '0', 'Empty', 1, '290', 13, 17),
        (756, 'D', '0', 'Empty', 1, '290', 13, 17),
        (757, 'D', '2', 'Regular', 1, '290', 13, 17),
        (758, 'D', '1', 'Regular', 1, '290', 13, 17),
        (759, 'E', '10', 'Regular', 1, '290', 13, 17),
        (760, 'E', '9', 'Regular', 1, '290', 13, 17),
        (761, 'E', '0', 'Empty', 1, '290', 13, 17),
        (762, 'E', '0', 'Empty', 1, '290', 13, 17),
        (763, 'E', '8', 'Regular', 1, '290', 13, 17),
        (764, 'E', '7', 'Regular', 1, '290', 13, 17),
        (765, 'E', '6', 'Regular', 1, '290', 13, 17),
        (766, 'E', '5', 'Regular', 1, '290', 13, 17),
        (767, 'E', '4', 'Regular', 1, '290', 13, 17),
        (768, 'E', '3', 'Regular', 1, '290', 13, 17),
        (769, 'E', '0', 'Empty', 1, '290', 13, 17),
        (770, 'E', '0', 'Empty', 1, '290', 13, 17),
        (771, 'E', '2', 'Regular', 1, '290', 13, 17),
        (772, 'E', '1', 'Regular', 1, '290', 13, 17),
        (773, 'A', '10', 'Regular', 1, '250', 13, 18),
        (774, 'A', '9', 'Regular', 1, '250', 13, 18),
        (775, 'A', '0', 'Empty', 1, '250', 13, 18),
        (776, 'A', '0', 'Empty', 1, '250', 13, 18),
        (777, 'A', '8', 'Regular', 1, '250', 13, 18),
        (778, 'A', '7', 'Regular', 1, '250', 13, 18),
        (779, 'A', '6', 'Regular', 1, '250', 13, 18),
        (780, 'A', '5', 'Regular', 1, '250', 13, 18),
        (781, 'A', '4', 'Regular', 1, '250', 13, 18),
        (782, 'A', '3', 'Regular', 1, '250', 13, 18),
        (783, 'A', '0', 'Empty', 1, '250', 13, 18),
        (784, 'A', '0', 'Empty', 1, '250', 13, 18),
        (785, 'A', '2', 'Regular', 1, '250', 13, 18),
        (786, 'A', '1', 'Regular', 1, '250', 13, 18),
        (787, 'B', '10', 'Regular', 1, '250', 13, 18),
        (788, 'B', '9', 'Regular', 1, '250', 13, 18),
        (789, 'B', '0', 'Empty', 1, '250', 13, 18),
        (790, 'B', '0', 'Empty', 1, '250', 13, 18),
        (791, 'B', '8', 'Regular', 1, '250', 13, 18),
        (792, 'B', '7', 'Regular', 1, '250', 13, 18),
        (793, 'B', '6', 'Regular', 1, '250', 13, 18),
        (794, 'B', '5', 'Regular', 1, '250', 13, 18),
        (795, 'B', '4', 'Regular', 1, '250', 13, 18),
        (796, 'B', '3', 'Regular', 1, '250', 13, 18),
        (797, 'B', '0', 'Empty', 1, '250', 13, 18),
        (798, 'B', '0', 'Empty', 1, '250', 13, 18),
        (799, 'B', '2', 'Regular', 1, '250', 13, 18),
        (800, 'B', '1', 'Regular', 1, '250', 13, 18),
        (801, 'C', '10', 'Regular', 1, '250', 13, 18),
        (802, 'C', '9', 'Regular', 1, '250', 13, 18),
        (803, 'C', '0', 'Empty', 1, '250', 13, 18),
        (804, 'C', '0', 'Empty', 1, '250', 13, 18),
        (805, 'C', '8', 'Regular', 1, '250', 13, 18),
        (806, 'C', '7', 'Regular', 1, '250', 13, 18),
        (807, 'C', '6', 'Regular', 1, '250', 13, 18),
        (808, 'C', '5', 'Regular', 1, '250', 13, 18),
        (809, 'C', '4', 'Regular', 1, '250', 13, 18),
        (810, 'C', '3', 'Regular', 1, '250', 13, 18),
        (811, 'C', '0', 'Empty', 1, '250', 13, 18),
        (812, 'C', '0', 'Empty', 1, '250', 13, 18),
        (813, 'C', '2', 'Regular', 1, '250', 13, 18),
        (814, 'C', '1', 'Regular', 1, '250', 13, 18),
        (815, 'D', '10', 'Regular', 1, '250', 13, 18),
        (816, 'D', '9', 'Regular', 1, '250', 13, 18),
        (817, 'D', '0', 'Empty', 1, '250', 13, 18),
        (818, 'D', '0', 'Empty', 1, '250', 13, 18),
        (819, 'D', '8', 'Regular', 1, '250', 13, 18),
        (820, 'D', '7', 'Regular', 1, '250', 13, 18),
        (821, 'D', '6', 'Regular', 1, '250', 13, 18),
        (822, 'D', '5', 'Regular', 1, '250', 13, 18),
        (823, 'D', '4', 'Regular', 1, '250', 13, 18),
        (824, 'D', '3', 'Regular', 1, '250', 13, 18),
        (825, 'D', '0', 'Empty', 1, '250', 13, 18),
        (826, 'D', '0', 'Empty', 1, '250', 13, 18),
        (827, 'D', '2', 'Regular', 1, '250', 13, 18),
        (828, 'D', '1', 'Regular', 1, '250', 13, 18),
        (829, 'E', '10', 'Regular', 1, '250', 13, 18),
        (830, 'E', '9', 'Regular', 1, '250', 13, 18),
        (831, 'E', '0', 'Empty', 1, '250', 13, 18),
        (832, 'E', '0', 'Empty', 1, '250', 13, 18),
        (833, 'E', '8', 'Regular', 1, '250', 13, 18),
        (834, 'E', '7', 'Regular', 1, '250', 13, 18),
        (835, 'E', '6', 'Regular', 1, '250', 13, 18),
        (836, 'E', '5', 'Regular', 1, '250', 13, 18),
        (837, 'E', '4', 'Regular', 1, '250', 13, 18),
        (838, 'E', '3', 'Regular', 1, '250', 13, 18),
        (839, 'E', '0', 'Empty', 1, '250', 13, 18),
        (840, 'E', '0', 'Empty', 1, '250', 13, 18),
        (841, 'E', '2', 'Regular', 1, '250', 13, 18),
        (842, 'E', '1', 'Regular', 1, '250', 13, 18);

        CREATE TABLE `theater` (
        `Theater_ID` int(11) NOT NULL,
        `Mall_ID` int(11) NOT NULL,
        `TheaterName` varchar(100) NOT NULL,
        `TotalSeats` int(11) NOT NULL,
        `TheaterType` varchar(50) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        INSERT INTO `theater` (`Theater_ID`, `Mall_ID`, `TheaterName`, `TotalSeats`, `TheaterType`) VALUES
        (13, 1, 'Director\'s Club 1', 50, 'Director\'s Club');

        CREATE TABLE `ticket` (
        `Ticket_ID` int(11) NOT NULL,
        `Seat_ID` int(11) NOT NULL,
        `Customer_ID` int(11) NOT NULL,
        `Movie_ID` int(11) NOT NULL,
        `TimeSlot_ID` int(11) NOT NULL,
        `Price` decimal(10,2) NOT NULL,
        `Status` int(11) NOT NULL,
        `DateTime` datetime NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        CREATE TABLE `timeslot` (
        `TimeSlot_ID` int(11) NOT NULL,
        `StartTime` tinytext NOT NULL,
        `EndTime` tinytext NOT NULL,
        `Date` tinytext NOT NULL,
        `ScreeningType` varchar(5) NOT NULL,
        `Movie_ID` int(11) NOT NULL,
        `Theater_ID` int(11) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

        INSERT INTO `timeslot` (`TimeSlot_ID`, `StartTime`, `EndTime`, `Date`, `ScreeningType`, `Movie_ID`, `Theater_ID`) VALUES
        (14, '18:30', '', '2025-11-20', '2D', 1, 13),
        (15, '13:50', '', '2025-11-22', '2D', 1, 13),
        (16, '18:50', '', '2025-11-21', '3D', 1, 13),
        (17, '18:50', '', '2025-11-21', '3D', 1, 13),
        (18, '18:15', '', '2025-11-22', '2D', 1, 13);


        ALTER TABLE `customer`
        ADD PRIMARY KEY (`Customer_ID`);

        ALTER TABLE `e-receipt`
        ADD PRIMARY KEY (`Receipt_ID`);

        ALTER TABLE `mall`
        ADD PRIMARY KEY (`Mall_ID`),
        ADD UNIQUE KEY `MallName` (`MallName`) USING HASH;

        ALTER TABLE `movie`
        ADD PRIMARY KEY (`Movie_ID`);

        ALTER TABLE `payment`
        ADD PRIMARY KEY (`Payment_ID`);

        ALTER TABLE `seats`
        ADD PRIMARY KEY (`Seat_ID`),
        ADD KEY `Theater_ID` (`Theater_ID`),
        ADD KEY `seats_ibfk_2` (`TimeSlot_ID`);

        ALTER TABLE `theater`
        ADD PRIMARY KEY (`Theater_ID`),
        ADD KEY `Mall_ID` (`Mall_ID`);

        ALTER TABLE `ticket`
        ADD PRIMARY KEY (`Ticket_ID`);

        ALTER TABLE `timeslot`
        ADD PRIMARY KEY (`TimeSlot_ID`),
        ADD KEY `Movie_ID` (`Movie_ID`),
        ADD KEY `Theater_ID` (`Theater_ID`);


        ALTER TABLE `customer`
        MODIFY `Customer_ID` int(11) NOT NULL AUTO_INCREMENT;

        ALTER TABLE `e-receipt`
        MODIFY `Receipt_ID` int(11) NOT NULL AUTO_INCREMENT;

        ALTER TABLE `mall`
        MODIFY `Mall_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

        ALTER TABLE `movie`
        MODIFY `Movie_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

        ALTER TABLE `payment`
        MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT;

        ALTER TABLE `seats`
        MODIFY `Seat_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=913;

        ALTER TABLE `theater`
        MODIFY `Theater_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

        ALTER TABLE `ticket`
        MODIFY `Ticket_ID` int(11) NOT NULL AUTO_INCREMENT;

        ALTER TABLE `timeslot`
        MODIFY `TimeSlot_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;


        ALTER TABLE `seats`
        ADD CONSTRAINT `seats_ibfk_1` FOREIGN KEY (`Theater_ID`) REFERENCES `theater` (`Theater_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD CONSTRAINT `seats_ibfk_2` FOREIGN KEY (`TimeSlot_ID`) REFERENCES `timeslot` (`TimeSlot_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

        ALTER TABLE `theater`
        ADD CONSTRAINT `theater_ibfk_1` FOREIGN KEY (`Mall_ID`) REFERENCES `mall` (`Mall_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

        ALTER TABLE `timeslot`
        ADD CONSTRAINT `timeslot_ibfk_1` FOREIGN KEY (`Movie_ID`) REFERENCES `movie` (`Movie_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD CONSTRAINT `timeslot_ibfk_2` FOREIGN KEY (`Theater_ID`) REFERENCES `theater` (`Theater_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

        /*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
        /*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
        /*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


        SQL;

        if ($conn->multi_query($sql)) {
            echo "yahooo";
        } else {
            echo "uh oh...";
        }
    }
    
?>

<!DOCTYPE html>
<html>
    <body>
        <main>
            <form id="createDatabase" name="createDatabase" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <button type="submit" name="createDatabase" value="createDatabase">Update Database</button>
            </form>

        </main>
    </body>
</html>
