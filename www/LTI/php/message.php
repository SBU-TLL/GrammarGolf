<?php

	function message($sourcedid, $grade) {
		$message = random_int(0,999999999);
		$operation = 'replaceResultRequest';
		return "<?xml version = '1.0' encoding = 'UTF-8'?>  
		<imsx_POXEnvelopeRequest xmlns = 'http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0'>
			<imsx_POXHeader>
				<imsx_POXRequestHeaderInfo>
					<imsx_version>V1.0</imsx_version>
					<imsx_messageIdentifier>$message</imsx_messageIdentifier>
				</imsx_POXRequestHeaderInfo>
			</imsx_POXHeader>
			<imsx_POXBody>
				<$operation>
					<resultRecord>
						<sourcedGUID>
							<sourcedId>$sourcedid</sourcedId>
						</sourcedGUID>
						<result>
							<resultScore>
								<language>en-us</language>
								<textString>$grade</textString>
							</resultScore>
						</result>
					</resultRecord>
				</$operation>
			</imsx_POXBody>
		</imsx_POXEnvelopeRequest>";
	};


?>
