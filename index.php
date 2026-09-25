<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Adsterra Direct Statistics</title>

<style>
*{box-sizing:border-box}

body{
    margin:0;
    background:#f3f5f7;
    color:#17202a;
    font-family:Arial,sans-serif
}

.wrap{
    max-width:1050px;
    margin:auto;
    padding:18px
}

header{
    background:#111827;
    color:white;
    border-radius:18px;
    padding:22px;
    margin-bottom:15px
}

h1{
    margin:0 0 5px;
    font-size:24px
}

.sub{
    opacity:.75;
    font-size:13px
}

.box{
    background:white;
    border:1px solid #e5e7eb;
    border-radius:16px;
    padding:16px;
    margin-bottom:15px
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:10px
}

label{
    font-size:12px;
    font-weight:bold;
    display:block;
    margin-bottom:5px
}

input,button{
    height:43px;
    width:100%;
    border:1px solid #d1d5db;
    border-radius:10px;
    padding:0 11px;
    font-size:14px
}

button{
    background:#111827;
    color:white;
    border:0;
    font-weight:bold;
    cursor:pointer
}

button:disabled{
    opacity:.5
}

.metrics{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:10px
}

.metric small{
    color:#6b7280
}

.metric b{
    display:block;
    font-size:22px;
    margin-top:6px
}

.table{
    overflow:auto
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:650px
}

th,td{
    padding:12px 9px;
    border-bottom:1px solid #eee;
    text-align:left;
    font-size:14px
}

th{
    font-size:11px;
    color:#6b7280
}

.money{
    font-weight:bold
}

#msg{
    font-size:13px;
    margin-top:9px
}

.err{
    color:#b91c1c
}

@media(max-width:650px){

    .grid{
        grid-template-columns:1fr 1fr
    }

    .grid button{
        grid-column:1/-1
    }

    .metrics{
        grid-template-columns:1fr 1fr
    }
}
</style>
</head>

<body>

<div class="wrap">

<header>
    <h1>Adsterra Direct Statistics</h1>
    <div class="sub">
        Statistik penghasilan berdasarkan periode dan Direct / Placement
    </div>
</header>


<div class="box">

    <div class="grid">

        <div>
            <label>DARI TANGGAL</label>
            <input id="start" type="date">
        </div>

        <div>
            <label>SAMPAI TANGGAL</label>
            <input id="finish" type="date">
        </div>

        <div>
            <label>API KEY ADSTERRA</label>
            <input
                id="key"
                type="password"
                placeholder="Masukkan API key"
            >
        </div>

        <div style="grid-column:1/-1">
            <button id="go">
                CEK STATISTIK
            </button>
        </div>

    </div>

    <div id="msg"></div>

</div>


<div class="metrics">

    <div class="box metric">
        <small>Revenue</small>
        <b id="rev">$0.0000</b>
    </div>

    <div class="box metric">
        <small>Impressions</small>
        <b id="imp">0</b>
    </div>

    <div class="box metric">
        <small>Clicks</small>
        <b id="click">0</b>
    </div>

    <div class="box metric">
        <small>CTR</small>
        <b id="ctr">0%</b>
    </div>

</div>


<div class="box table">

<table>

<thead>

<tr>
    <th>DIRECT / SUB ID</th>
    <th>IMPRESSIONS</th>
    <th>CLICKS</th>
    <th>CTR</th>
    <th>CPM</th>
    <th>REVENUE</th>
</tr>

</thead>

<tbody id="rows">

<tr>
    <td colspan="6">
        Masukkan API key dan pilih tanggal.
    </td>
</tr>

</tbody>

</table>

</div>

</div>


<script>

const $ = x => document.getElementById(x);


// ==============================
// DEFAULT TANGGAL
// ==============================

const today = new Date();

const iso = d =>
    d.toISOString().slice(0,10);

document.getElementById("finish").value =
    iso(today);

let d = new Date(today);

d.setDate(d.getDate() - 6);

document.getElementById("start").value =
    iso(d);


// ==============================
// FUNGSI BACA DATA
// ==============================

function numberValue(obj, keys){

    for(const key of keys){

        if(
            obj &&
            obj[key] !== undefined &&
            obj[key] !== null
        ){

            return Number(obj[key]) || 0;

        }

    }

    return 0;
}


function textValue(obj, keys){

    for(const key of keys){

        if(
            obj &&
            obj[key] !== undefined &&
            obj[key] !== null
        ){

            return obj[key];

        }

    }

    return "";

}


function getRows(data){

    if(Array.isArray(data))
        return data;

    if(Array.isArray(data.data))
        return data.data;

    if(Array.isArray(data.results))
        return data.results;

    if(Array.isArray(data.stats))
        return data.stats;

    return [];

}


// ==============================
// CEK STATISTIK
// ==============================

document.getElementById("go").onclick =
async function(){

    const key =
        document.getElementById("key").value.trim();

    const start =
        document.getElementById("start").value;

    const finish =
        document.getElementById("finish").value;


    if(!key){

        document.getElementById("msg").textContent =
            "Masukkan API key Adsterra.";

        return;

    }


    if(!start || !finish){

        document.getElementById("msg").textContent =
            "Pilih tanggal terlebih dahulu.";

        return;

    }


    if(start > finish){

        document.getElementById("msg").textContent =
            "Tanggal awal tidak boleh lebih besar dari tanggal akhir.";

        return;

    }


    const button =
        document.getElementById("go");

    button.disabled = true;

    document.getElementById("msg").textContent =
        "Mengambil statistik Adsterra...";


    try{


        // ==============================
        // API ADSTERRA
        // ==============================

        const url =
            "https://api3.adsterratools.com/publisher/stats.json?"
            +
            new URLSearchParams({

                start_date:start,

                finish_date:finish,

                "group_by[]":"placement_sub_id"

            });


        const response =
            await fetch(

                url,

                {

                    method:"GET",

                    headers:{

                        "Accept":
                            "application/json",

                        "X-API-Key":
                            key

                    }

                }

            );


        const data =
            await response.json();


        if(!response.ok){

            throw new Error(

                data.message ||
                data.error ||
                "HTTP " + response.status

            );

        }


        const rows =
            getRows(data);


        let totalImpressions = 0;

        let totalClicks = 0;

        let totalRevenue = 0;


        const tbody =
            document.getElementById("rows");

        tbody.innerHTML = "";


        if(rows.length === 0){

            tbody.innerHTML =

                '<tr>' +
                '<td colspan="6">' +
                'Tidak ada data untuk periode tersebut.' +
                '</td>' +
                '</tr>';

        }


        rows.forEach(function(item){


            const impressions =
                numberValue(

                    item,

                    [
                        "impressions",
                        "impression"
                    ]

                );


            const clicks =
                numberValue(

                    item,

                    [
                        "clicks",
                        "click"
                    ]

                );


            const revenue =
                numberValue(

                    item,

                    [
                        "revenue",
                        "earnings",
                        "earning"
                    ]

                );


            const ctr =
                impressions > 0

                ?

                clicks /
                impressions *
                100

                :

                numberValue(
                    item,
                    ["ctr"]
                );


            const cpm =
                impressions > 0

                ?

                revenue /
                impressions *
                1000

                :

                numberValue(
                    item,
                    ["cpm"]
                );


            const direct =
                textValue(

                    item,

                    [
                        "placement_sub_id",
                        "placementSubId",
                        "sub_id",
                        "subid",
                        "placement"
                    ]

                ) || "Unknown";


            totalImpressions +=
                impressions;

            totalClicks +=
                clicks;

            totalRevenue +=
                revenue;


            const safeDirect =
                String(direct)
                .replace(
                    /[<>&"]/g,
                    ""
                );


            tbody.insertAdjacentHTML(

                "beforeend",

                `
                <tr>

                    <td>
                        <b>${safeDirect}</b>
                    </td>

                    <td>
                        ${impressions.toLocaleString("id-ID")}
                    </td>

                    <td>
                        ${clicks.toLocaleString("id-ID")}
                    </td>

                    <td>
                        ${ctr.toFixed(2)}%
                    </td>

                    <td>
                        $${cpm.toFixed(4)}
                    </td>

                    <td class="money">
                        $${revenue.toFixed(4)}
                    </td>

                </tr>
                `

            );

        });


        // ==============================
        // TOTAL
        // ==============================

        document.getElementById("rev").textContent =
            "$" + totalRevenue.toFixed(4);


        document.getElementById("imp").textContent =
            totalImpressions.toLocaleString("id-ID");


        document.getElementById("click").textContent =
            totalClicks.toLocaleString("id-ID");


        document.getElementById("ctr").textContent =

            (

                totalImpressions > 0

                ?

                totalClicks /
                totalImpressions *
                100

                :

                0

            ).toFixed(2) + "%";


        document.getElementById("msg").textContent =
            "Berhasil mengambil data " +
            start +
            " sampai " +
            finish;


    }

    catch(error){

        document.getElementById("msg").className =
            "err";

        document.getElementById("msg").textContent =
            "Gagal: " + error.message;

    }


    button.disabled = false;

};

</script>

</body>
</html>
