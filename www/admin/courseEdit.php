<?php
// Course editor — admin only. The guard requires a Shibboleth login AND
// membership in GRAMMARGOLF_ADMINS (root .env); everyone else gets 403.
require_once dirname(__DIR__, 2) . '/lib/gg_auth.php';
gg_require_admin();

// problem_sets/ lives at the web root, one level up from this admin/ directory.
$problem_set=glob(__DIR__ . "/../problem_sets/" . "*");
$title = array();
$selectItems= array();
foreach ($problem_set as $key => $value) {
    $JSON = json_decode(file_get_contents($value));
    // print_r($value);
    $jsonID=preg_match('/problem_sets[^_]*_([^\.]*)/',$value, $match);
    $selectItems[(int)$match[1]]= $JSON->title;
}
// Numeric order, so the dropdown reads 1,2,3… rather than glob's 1,14,15,2,20.
ksort($selectItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="/scripts/JSON_API.js"></script>
    <script> 
    document.addEventListener('DOMContentLoaded', ()=>loadCourse(), { once: true });

    // let courseCount = <?php echo json_encode(glob("./problem_sets/". "*"));?>;
    let courseList = <?php echo json_encode($selectItems);?>;
    console.log(courseList)

 function loadCourse(courseID=1){
     JSON_API(undefined,courseID,undefined, "admin")
         .then((data) => {
             problemJSON = data
             init(problemJSON, courseID)
         })
 }

     function resizeInput(inputEl) {
     inputEl.style.height = Math.min(inputEl.scrollHeight, 3000000) + "px";
     inputEl.style.width = Math.min(inputEl.scrollWidth, 3000000) + "px";
     };
        
     function init(problemJSON, courseID=1) 
 {document.body.innerHTML = ""
 let select = Object.assign(document.createElement("select"),{id:"select"})
 let defaultOption = document.createElement("option");
 defaultOption.text = "Select from here";
 defaultOption.value = ""; // Empty value for the placeholder
 defaultOption.disabled = true; // Prevents the user from actively selecting the placeholder
 defaultOption.selected = true;
 select.appendChild(defaultOption);
 for (const [courseID, courseTitle] of Object.entries(courseList)) {
    console.log("ID:", courseID);
    console.log("Title:", courseTitle);
    // let courseID_select=courseCount[i]?.split(".json")[0].split("_").pop()
    // let courseString=`course_${courseID}`;
    // Show the id alongside the title: it is what identifies the problem set
    // everywhere else — the file (problem_sets/problem_<id>.json) and the URL
    // players use (/public/?problem_id=<id>).
    let option = Object.assign(document.createElement("option"),{innerHTML:`${courseID} — ${courseTitle}`, value:`${courseID}`})
     select.appendChild(option);
 }
 document.body.append(select);
 introText(`Select Course: `, select);
//  document.getElementById('select').options.selectedIndex = courseID-1
 document.getElementById("select").addEventListener('change', ()=>changeCourse());
     document.body.append(Object.assign(document.createElement("button"),{id:"submit", innerHTML:"Submit"})) 
     br()
     const titleSpan = document.createElement('span');
     let pageTitle = document.createTextNode(`${problemJSON.title}`);
     titleSpan.append(pageTitle);
     titleSpan.style.fontSize= '30px'
     titleSpan.style.fontWeight = 'bold';
     document.body.append(titleSpan);
     br()
     // Which problem set is on screen, and how to reach it as a player.
     const idLine = document.createElement('div');
     idLine.style.margin = '4px 0 12px';
     idLine.innerHTML = `problem set ID <strong>${courseID}</strong>`
         + ` &nbsp;·&nbsp; file <code>problem_sets/problem_${courseID}.json</code>`
         + ` &nbsp;·&nbsp; play: <a href="/public/?problem_id=${courseID}" target="_blank">/public/?problem_id=${courseID}</a>`
         + ` | <a href="/brightspace/?problem_id=${courseID}" target="_blank">/brightspace/?problem_id=${courseID}</a>`;
     document.body.append(idLine);
     br()
     var i = 1;
     problemJSON.holes.forEach(hole=> {
         let expressionArea=Object.assign(document.createElement("textArea"),{className:"expression", value:hole.expression, style:"width:35rem"})
         let descArea=Object.assign(document.createElement("input"),{className: "notes",type:"text", value:hole.notes, style:"width:40rem"})
         document.body.append(expressionArea)  
         introText(`hole ${i}'s expression: `, expressionArea);      
         br()
         document.body.append(descArea)
         introText(`note: `, descArea);  
         br()
         i +=1;
     });
     let title = Object.assign(document.createElement("textArea"),{className:"title",innerHTML:problemJSON.title, style:"width:20rem"})
     document.body.append(title)
     introText(`title: `, title);  
     br()
     let description = Object.assign(document.createElement("textArea"),{className:"description",innerHTML:problemJSON.description, style:"width:20rem"})
     document.body.append(description)
     introText(`description: `, description);  
     document.querySelectorAll("textArea").forEach(resizeInput)
     document.getElementById("submit").addEventListener('click', ()=>submitCourse(courseID), { once: true });


 }
 function changeCourse(){
    let selectElement = document.getElementById("select");
     let courseID = selectElement.value;
     console.log(courseID);
     loadCourse(courseID);
     selectElement.selectedIndex = 0;
 }
 function submitCourse(courseID){
     console.log(courseID)
     let expressions = document.querySelectorAll(".expression")
     let allNotes =  document.querySelectorAll(".notes")
     let title =  document.querySelector(".title")
     let description=  document.querySelector(".description")
     console.log(expressions)
     let problemJSON = {holes:[], description:description.value, title:title.value}
     expressions.forEach((expression, i)=>{
         console.log(expression.value)
         let notes = allNotes[i]
         problemJSON.holes[i] = {expression:expression.value, notes:notes.value}
          console.log(problemJSON.holes[i].value)
     })
     // Wait for the save before claiming it worked, and reload only afterwards.
     // This used to alert "successfully submitted" the moment the request was
     // fired — so a rejected or failed save still looked like it had worked —
     // and the reload raced the POST, sometimes redisplaying the old content.
     JSON_API(problemJSON, courseID, "POST", "admin")
         .then(() => {
             alert(`Problem set ${courseID} saved.`);
             loadCourse(courseID);
         })
         .catch(() => {
             alert(`Problem set ${courseID} was NOT saved.\n\n`
                 + `The server rejected the write. Common causes: your netID is not in\n`
                 + `GRAMMARGOLF_ADMINS, or problem_sets/ is not writable by the web server.\n`
                 + `See the browser console and the server error log for the reason.`);
             loadCourse(courseID);
         });
 }
 function br(){
     document.body.append(document.createElement("br"))
 }

 function introText(txt, el) {
     var text = document.createTextNode(txt);
     el.parentNode.insertBefore(text, el);
 }


         </script>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Grammar Golf — Course Editor</title>
 </head>
 <body>
    
 </body>
 </html>
