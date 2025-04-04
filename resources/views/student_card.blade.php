<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="idCard.css">
    <title>ID Card</title>
<!--
    So lets start -->
</head>
<body>
        <div class="container">
            <div class="padding">
                <div class="font">
                    <div class="top">
                        <img src="{{$student->avatar}}" alt="" width="100px" height="100px">
                    </div>
                    <div class="bottom">
                        <p>{{ $student->name }}</p>
                        <p class="desi">{{ $student->studentSchool->name  }}</p>
                        <div class="barcode">
                            <img src="{{$student->qr_code}}" alt="">
                        </div>
                        <br>
                        <p class="no">{{ $student->student_identification }}</p>
                    </div>
                </div>
            </div>
        </div>
</body>
</html>


<style>
*{
    margin: 00px;
    padding: 00px;
    box-sizing: content-box;
    font-family: DejaVu Serif, serif;
}
.container {
    height: 100vh;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: row;
    flex-flow: wrap;

}

.font{
    margin: 10px;
    height: 475px;
    width: 350px;
    position: relative;
    border-radius: 10px;
    border: 1px solid #8338ec;
}

.top{
    height: 30%;
    width: 100%;
    background-color: #8338ec;
    position: relative;
    z-index: 5;
    border-top-left-radius: 15px;
    border-top-right-radius: 15px;
}

.bottom{
    height: 70%;
    width: 100%;
    background-color: white;
    position: absolute;
    border-bottom-left-radius: 15px;
    border-bottom-right-radius: 15px;
}

.top img{
    height: 120px;
    width: 120px;
    background-color: #e6ebe0;
    border-radius: 10px;
    position: absolute;
    top:50px;
    left: 120px;
}
.bottom p{
    position: relative;
    top: 60px;
    text-align: center;
    text-transform: capitalize;
    font-weight: bold;
    font-size: 20px;
    text-emphasis: spacing;
}
.bottom .desi{
    font-size:12px;
    color: grey;
    font-weight: normal;
}
.bottom .no{
    font-size: 15px;
    font-weight: normal;
}
.barcode img
{
    height: 135px;
    width: 135px;
    text-align: center;
    margin: 5px;
}
.barcode{
    text-align: center;
    position: relative;
    top: 70px;
}

.back
{
    height: 375px;
    width: 250px;
    border-radius: 10px;
    background-color: #8338ec;

}
.qr img{
    height: 80px;
    width: 100%;
    margin: 20px;
    background-color: white;
}
.Details {
    color: white;
    text-align: center;
    padding: 10px;
    font-size: 25px;
}


.details-info{
    color: white;
    text-align: left;
    padding: 5px;
    line-height: 20px;
    font-size: 16px;
    text-align: center;
    margin-top: 20px;
    line-height: 22px;
}

.logo {
    height: 40px;
    width: 150px;
    padding: 40px;
}

.logo img{
    height: 100%;
    width: 100%;
    color: white ;

}
.padding{
    padding-right: 20px;
}

@media screen and (max-width:400px)
{
    .container{
        height: 130vh;
    }
    .container .front{
        margin-top: 50px;
    }
}
@media screen and (max-width:600px)
{
    .container{
        height: 130vh;
    }
    .container .front{
        margin-top: 50px;
    }

}
</style>
